<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\WooClient;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProductUpsertService
 *
 * Führt ein robustes Upsert (Create/Update) von WooCommerce-Hauptprodukten durch
 * und nutzt dabei einen SKU-Preflight (per WooProductLookupService), um
 * Duplicate-SKU-Fehler beim POST zu vermeiden.
 *
 * Wichtige Punkte:
 * - Für variable Produkte ist es Best Practice, **am Parent keine SKU** zu setzen.
 *   (Variante besitzt die SKU). Falls dennoch eine SKU im Payload übergeben wird,
 *   nutzt der Preflight diese zur Update-Erkennung.
 * - Preise werden in diesem Projekt bewusst NICHT synchronisiert.
 * - Nach erfolgreichem Create (POST) wird die Remote-ID in products.woo_product_id gespeichert.
 *
 * Konfiguration:
 * - Base URL, Version, Credentials: config('woo.api.*'), config('woo.default_api_version')
 *
 * Integration:
 * - Aus deinem bestehenden Produkt-Exporter/Service statt direktem POST/PUT:
 *     app(ProductUpsertService::class)->upsertProduct($product, $payload);
 *
 * Payload-Erwartung (Auszug, Woo-REST /products):
 * - Titel/Name, Beschreibung, Typ (simple|variable), Bilder, Attribute etc.
 * - SKU optional (für variable Eltern i. d. R. leer lassen)
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class ProductUpsertService
{
  protected PendingRequest $http;
  protected string $base;
  protected string $ver;

  public function __construct(
    protected WooProductLookupService $lookup
  ) {
    $this->base = rtrim((string) config('woo.api.base_url'), '/');
    $this->ver  = (string) config('woo.default_api_version', 'wc/v3');

    $this->http = Http::baseUrl($this->base . '/wp-json/' . $this->ver)
      ->withBasicAuth(
        (string) config('woo.api.key'),
        (string) config('woo.api.secret')
      )
      ->withOptions([
        'curl' => [
          CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
          CURLOPT_DNS_CACHE_TIMEOUT => 60,
        ],
      ])
      ->acceptJson()
      ->asJson()
      ->retry(4, 200);
  }

  /**
   * Liefert einen WooClient für den Shop des Produkts (Fallback: first()).
   */
  private function makeClientFor(Product $product): WooClient
  {
    /** @var Shop|null $shop */
    $shop = method_exists($product, 'shop') ? $product->shop : null;
    if (!$shop) {
      $shop = Shop::query()->firstOrFail();
    }
    return new WooClient($shop);
  }

  /**
   * Prüft, ob ein Woo-Produkt mit gegebener ID existiert.
   */
  private function remoteProductExists(Product $product, int $remoteId): bool
  {
    $client = $this->makeClientFor($product);

    try {
      $client->get("products/{$remoteId}");
      return true;
    } catch (ClientException $e) {
      $code = $e->getResponse()?->getStatusCode();
      $body = (string) ($e->getResponse()?->getBody() ?? '');
      if ($code === 404) {
        return false;
      }
      if ($code === 400 && str_contains($body, 'woocommerce_rest_product_invalid_id')) {
        return false;
      }
      throw $e; // andere Client-Fehler weiterreichen
    }
  }

  /**
   * Upsert eines Hauptprodukts: PUT (wenn ID bekannt/gefunden), sonst POST.
   *
   * @param  Product               $product
   * @param  array<string,mixed>   $payload
   * @param  bool                  $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  public function upsertProduct(Product $product, array $payload, bool $failHard = false): array
  {
    /** @var \App\Services\Woo\WooClient $client */
    $client = app(\App\Services\Woo\WooClient::class);

    $endpointBase = 'products';

    // --- Response-Normalisierung (PSR-7 oder Array) ---
    $parseResponse = function ($resp): array {
      // PSR-7?
      if (is_object($resp) && method_exists($resp, 'getStatusCode') && method_exists($resp, 'getBody')) {
        $status = (int) $resp->getStatusCode();
        $raw    = (string) $resp->getBody();
        $body   = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        return ['status' => $status, 'body' => $body];
      }
      // Array-Shape (diverse Varianten erlauben)
      if (is_array($resp)) {
        $status = (int) ($resp['status'] ?? $resp['statusCode'] ?? 200);
        $body   = $resp['body'] ?? $resp['data'] ?? $resp;
        if (!is_array($body)) {
          $body = is_string($body) && $body !== '' ? (json_decode($body, true) ?: []) : [];
        }
        return ['status' => $status, 'body' => $body];
      }
      // Fallback
      return ['status' => 200, 'body' => []];
    };

    // --- 1) Preflight (nur ohne bekannte Woo-ID) ---
    $remoteId = (int) ($product->woo_product_id ?? 0);

    if ($remoteId <= 0 && !empty($payload['sku']) && class_exists(\App\Services\Woo\WooProductLookupService::class)) {
      try {
        /** @var \App\Services\Woo\WooProductLookupService $lookup */
        $lookup  = app(\App\Services\Woo\WooProductLookupService::class);
        $foundId = (int) ($lookup->findProductIdBySku((string) $payload['sku']) ?? 0);
        if ($foundId > 0) {
          $remoteId = $foundId; // Lokal erst nach erfolgreichem PUT schreiben
        }
      } catch (\Throwable $e) {
        // Lookup-Fehler nicht kritisch
      }
    }

    $isUpdate = $remoteId > 0;

    try {
      if ($isUpdate) {
        // PUT /products/{id}
        $resp = $client->put("{$endpointBase}/{$remoteId}", $payload);
        $norm = $parseResponse($resp);

        return [
          'action'    => 'updated',
          'status'    => $norm['status'],
          'remote_id' => (int) ($norm['body']['id'] ?? $remoteId),
          'body'      => $norm['body'],
        ];
      }

      // POST /products
      $resp = $client->post($endpointBase, $payload);
      $norm = $parseResponse($resp);

      $newId = (int) ($norm['body']['id'] ?? 0);
      if ($newId > 0) {
        $product->woo_product_id = $newId;
        $product->save();
      }

      return [
        'action'    => 'created',
        'status'    => $norm['status'],
        'remote_id' => $newId ?: null,
        'body'      => $norm['body'],
      ];
    } catch (\Throwable $e) {
      $msg = $e->getMessage();

      $isInvalidId =
        str_contains($msg, 'woocommerce_rest_product_invalid_id') ||
        str_contains($msg, '"code":"woocommerce_rest_product_invalid_id"') ||
        str_contains($msg, 'Invalid ID');

      if ($failHard) {
        throw $e;
      }

      return [
        'action'    => $isUpdate ? 'update-failed' : 'create-failed',
        'status'    => $isUpdate ? 400 : 400,
        'remote_id' => $isUpdate ? $remoteId : null,
        'body'      => ['error' => $msg, 'invalid_id' => $isInvalidId],
      ];
    }
  }



  /**
   * PUT /products/{id}
   *
   * @param  int                  $wooProductId
   * @param  Product              $product
   * @param  array<string,mixed>  $payload
   * @param  bool                 $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  protected function updateExisting(int $wooProductId, Product $product, array $payload, bool $failHard): array
  {
    $url = "/products/{$wooProductId}";

    // BEVOR $woo->post(...) oder $woo->put(...):

    $source = app()->runningInConsole() ? 'cli' : 'dashboard';

    Log::debug('ProductUpsertService: upsert payload', [
      'source'   => $source,
      'action'   => isset($wooProductId) ? 'update' : 'create',
      'product_id' => $product->id ?? null,
      'payload'  => $payload,
    ]);


    try {
      $resp = $this->http->put($url, $payload);
      if ($resp->failed()) {
        $this->logHttpError('PUT product', $resp, ['product_id' => $product->id, 'woo_product_id' => $wooProductId]);
        if ($failHard) {
          $this->throwHttp('PUT product', $resp);
        }
      } else {
        Log::info('ProductUpsertService: product updated', [
          'product_id' => $product->id,
          'woo_product_id' => $wooProductId,
          'status' => $resp->status(),
        ]);
      }

      return [
        'action'    => 'updated',
        'status'    => $resp->status(),
        'remote_id' => $wooProductId,
        'body'      => $resp->json(),
      ];
    } catch (\Throwable $e) {
      Log::error('ProductUpsertService: exception on update', [
        'product_id' => $product->id,
        'woo_product_id' => $wooProductId,
        'error' => $e->getMessage(),
      ]);
      if ($failHard) {
        throw $e;
      }
      return ['action' => 'error', 'status' => 0, 'remote_id' => $wooProductId, 'body' => null];
    }
  }

  /**
   * POST /products
   *
   * @param  Product              $product
   * @param  array<string,mixed>  $payload
   * @param  bool                 $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  protected function createNew(Product $product, array $payload, bool $failHard): array
  {
    $url = "/products";

    // BEVOR $woo->post(...) oder $woo->put(...):

    $source = app()->runningInConsole() ? 'cli' : 'dashboard';

    Log::debug('ProductUpsertService: upsert payload', [
      'source'   => $source,
      'action'   => isset($wooProductId) ? 'update' : 'create',
      'product_id' => $product->id ?? null,
      'payload'  => $payload,
    ]);


    try {
      $resp = $this->http->post($url, $payload);

      if ($resp->failed()) {
        $this->logHttpError('POST product', $resp, ['product_id' => $product->id]);
        if ($failHard) {
          $this->throwHttp('POST product', $resp);
        }

        return [
          'action'    => 'error',
          'status'    => $resp->status(),
          'remote_id' => null,
          'body'      => $resp->json(),
        ];
      }

      $data = $resp->json();
      $remoteId = is_array($data) ? ($data['id'] ?? null) : null;

      if (!empty($remoteId)) {
        $product->woo_product_id = (int) $remoteId;
        $product->save();
      }

      Log::info('ProductUpsertService: product created', [
        'product_id' => $product->id,
        'woo_product_id' => $remoteId,
        'status' => $resp->status(),
      ]);

      return [
        'action'    => 'created',
        'status'    => $resp->status(),
        'remote_id' => $remoteId ? (int) $remoteId : null,
        'body'      => $data,
      ];
    } catch (\Throwable $e) {
      Log::error('ProductUpsertService: exception on create', [
        'product_id' => $product->id,
        'error' => $e->getMessage(),
      ]);
      if ($failHard) {
        throw $e;
      }
      return ['action' => 'error', 'status' => 0, 'remote_id' => null, 'body' => null];
    }
  }

  /**
   * Hilfs-Logging für HTTP-Fehler.
   *
   * @param  string   $action
   * @param  Response $resp
   * @param  array<string,mixed> $ctx
   * @return void
   */
  protected function logHttpError(string $action, Response $resp, array $ctx = []): void
  {
    $body = $resp->json();
    Log::error("ProductUpsertService: {$action} failed", array_merge($ctx, [
      'status' => $resp->status(),
      'body'   => is_array($body) ? $body : $resp->body(),
    ]));
  }

  /**
   * Wirft eine Exception mit Response-Details.
   *
   * @param  string   $action
   * @param  Response $resp
   * @return never
   */
  protected function throwHttp(string $action, Response $resp)
  {
    $body = $resp->json();
    $msg  = is_array($body) ? json_encode($body) : (string) $resp->body();
    throw new \RuntimeException("Woo API {$action} failed: HTTP {$resp->status()} {$msg}");
  }
}
