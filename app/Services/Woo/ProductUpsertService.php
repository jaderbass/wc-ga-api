<?php

namespace App\Services\Woo;

use App\Models\Product;
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
      ->acceptJson()
      ->asJson()
      ->retry(2, 250);
  }

  /**
   * Upsert eines Hauptprodukts: PUT (wenn ID bekannt/gefunden), sonst POST.
   *
   * Ablauf:
   * 1) Wenn $product->woo_product_id gesetzt → PUT.
   * 2) Sonst: Wenn $payload['sku'] vorhanden → SKU-Preflight:
   *      - existiert Produkt? → ID setzen, PUT
   *      - sonst POST
   * 3) Nach POST: remote ID speichern in products.woo_product_id
   *
   * @param  Product               $product  Lokales Produktmodell (enthält woo_product_id|null)
   * @param  array<string,mixed>   $payload  Woo-/products-Payload (ohne Preisfelder)
   * @param  bool                  $failHard Exceptions bei HTTP-Fehlern werfen?
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  public function upsertProduct(Product $product, array $payload, bool $failHard = false): array
  {
    // 0) Safety: Base-Konfig prüfen
    if (empty(config('woo.api.base_url')) || empty(config('woo.api.key')) || empty(config('woo.api.secret'))) {
      $msg = 'Missing Woo API config (base_url, key, secret).';
      Log::error('ProductUpsertService: ' . $msg, ['product_id' => $product->id]);
      if ($failHard) {
        throw new \RuntimeException($msg);
      }
      return ['action' => 'skipped', 'status' => 0, 'remote_id' => null, 'body' => null];
    }

    // 1) Falls lokale Remote-ID schon da → Update
    if (!empty($product->woo_product_id)) {
      return $this->updateExisting((int) $product->woo_product_id, $product, $payload, $failHard);
    }

    // 2) Kein woo_product_id: per SKU prüfen (falls im Payload vorhanden)
    $sku = $payload['sku'] ?? null;
    if (!empty($sku)) {
      $existingId = $this->lookup->findProductIdBySku($sku);
      if ($existingId !== null) {
        // ID lokal persistieren, damit künftige Syncs immer PUT nutzen
        $product->woo_product_id = $existingId;
        $product->save();

        Log::info('ProductUpsertService: Preflight hit, switching to UPDATE', [
          'product_id' => $product->id,
          'woo_product_id' => $existingId,
          'sku' => $sku,
        ]);

        return $this->updateExisting($existingId, $product, $payload, $failHard);
      }
    }

    // 3) Create (POST)
    return $this->createNew($product, $payload, $failHard);
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
