<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
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
  /**
   * Upsert eines Hauptprodukts: PUT (wenn ID bekannt/gefunden), sonst POST.
   *
   * - Preise werden NICHT synchronisiert.
   * - Für variable Produkte wird die Attributliste aus den Varianten aufgebaut:
   *   [
   *     ['name' => 'pa_size',  'position' => 0, 'visible' => true, 'variation' => true, 'options' => ['S','M','L']],
   *     ['name' => 'pa_color', 'position' => 1, 'visible' => true, 'variation' => true, 'options' => ['Blue','Red']]
   *   ]
   *
   * @param  Product               $product
   * @param  array<string,mixed>   $payload  (ohne Preise)
   * @param  bool                  $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  public function upsertProduct(Product $product, array $payload, bool $failHard = false): array
  {
    /** @var \App\Services\Woo\WooClient $client */
    $client = app(\App\Services\Woo\WooClient::class);

    // 0) Sicherstellen: keine Preisfelder am Parent
    unset($payload['regular_price'], $payload['sale_price'], $payload['price']);

    // 1) Parent-Attribute aus Varianten ableiten (macht Parent sichtbar & variabel)
    $payload = $this->ensureParentAttributes($product, $payload);

    // --- Response-Normalisierung (PSR-7 oder Array) ---
    $parseResponse = function ($resp): array {
      if (is_object($resp) && method_exists($resp, 'getStatusCode') && method_exists($resp, 'getBody')) {
        $status = (int) $resp->getStatusCode();
        $raw    = (string) $resp->getBody();
        $body   = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        return ['status' => $status, 'body' => $body];
      }
      if (is_array($resp)) {
        $status = (int) ($resp['status'] ?? $resp['statusCode'] ?? 200);
        $body   = $resp['body'] ?? $resp['data'] ?? $resp;
        if (!is_array($body)) {
          $body = is_string($body) && $body !== '' ? (json_decode($body, true) ?: []) : [];
        }
        return ['status' => $status, 'body' => $body];
      }
      return ['status' => 200, 'body' => []];
    };

    $endpointBase = 'products';
    $remoteId     = (int) ($product->woo_product_id ?? 0);

    try {
      if ($remoteId > 0) {
        // UPDATE
        $resp = $client->put("{$endpointBase}/{$remoteId}", $payload);
        $norm = $parseResponse($resp);

        return [
          'action'    => 'updated',
          'status'    => $norm['status'],
          'remote_id' => (int) ($norm['body']['id'] ?? $remoteId),
          'body'      => $norm['body'],
        ];
      }

      // CREATE
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
        'action'    => $remoteId > 0 ? 'update-failed' : 'create-failed',
        'status'    => $remoteId > 0 ? 400 : 400,
        'remote_id' => $remoteId > 0 ? $remoteId : null,
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

  /**
   * Stellt sicher, dass der Parent ein korrektes Woo-Attribut-Setup hat.
   * - nutzt WooAttributeResolver (falls vorhanden), sonst Fallback auf DB-Varianten
   * - setzt type='variable' und attributes[] mit options
   * - entfernt Preisfelder am Parent (Sicherheit)
   *
   * @param  Product               $product
   * @param  array<string,mixed>   $payload
   * @return array<string,mixed>
   */
  private function ensureParentAttributes(Product $product, array $payload): array
  {
    // Preise am Parent nie mitsenden
    unset($payload['regular_price'], $payload['sale_price'], $payload['price']);

    // 1) Bevorzugt: Resolver verwenden (falls gebunden)
    $attributes = [];
    if (app()->bound(\App\Services\Woo\WooAttributeResolver::class)) {
      /** @var mixed $resolver */
      $resolver = app(\App\Services\Woo\WooAttributeResolver::class);

      // Versuche diverse sinnvolle Methoden, ohne die konkrete Signatur zu erzwingen.
      // Erwartete Rückgabe-Form bei "direkten" Methoden: array<int,array{name,options[],visible,variation,position?}>
      // Alternativ: Map slug => options[]; wir konvertieren dann selbst zu Woo-Attributes.
      $attributes = $this->getAttributesFromResolver($resolver, $product);
    }

    // 2) Fallback: aus den DB-Varianten selbst aggregieren
    if (empty($attributes)) {
      $attrValues = $this->collectVariantAttributes($product); // Map: slug => [options...]
      if (!empty($attrValues)) {
        $pos = 0;
        foreach ($attrValues as $slug => $options) {
          if (empty($options)) {
            continue;
          }
          $attributes[] = [
            'name'      => $slug,                               // z. B. 'pa_size'
            'position'  => $pos++,
            'visible'   => true,
            'variation' => true,
            'options'   => array_values(array_unique($options)),
          ];
        }
      }
    }

    // 3) Wenn Attribute vorhanden → Parent als 'variable' markieren + attributes setzen
    if (!empty($attributes)) {
      $payload['type'] = 'variable';
      $payload['attributes'] = $attributes;

      // Sichtbarkeit standardisieren (manche Themes verstecken sonst)
      $payload['status'] = $payload['status'] ?? 'publish';
      $payload['catalog_visibility'] = $payload['catalog_visibility'] ?? 'visible';
    }

    return $payload;
  }

  /**
   * Liest Parent-Attribute über den WooAttributeResolver (versch. mögliche Methodennamen).
   * Gibt entweder fertige Woo-Attributes zurück, oder baut sie aus einer slug=>options Map.
   *
   * @return array<int,array{name:string,options:array,visible:bool,variation:bool,position?:int}>
   */
  private function getAttributesFromResolver($resolver, \App\Models\Product $product): array
  {
    try {
      // 1) Direkte "fertige" Attribute?
      foreach (['attributesForParent', 'buildParentAttributes', 'resolveParentAttributes'] as $method) {
        if (method_exists($resolver, $method)) {
          $res = $resolver->{$method}($product);
          if (is_array($res) && !empty($res)) {
            // Wir akzeptieren hier bereits die Woo-Shape
            return array_values($res);
          }
        }
      }

      // 2) Map slug => options[] und selbst in Woo-Attributes umwandeln
      foreach (['collectVariantAttributes', 'variantOptionsForParent', 'optionsForProduct'] as $method) {
        if (method_exists($resolver, $method)) {
          $map = $resolver->{$method}($product); // erwarten: ['pa_size'=>['S','M'], ...]
          if (is_array($map) && !empty($map)) {
            $attrs = [];
            $pos = 0;
            foreach ($map as $slug => $options) {
              if (!is_array($options) || empty($options)) {
                continue;
              }
              $attrs[] = [
                'name'      => (string) $slug,
                'position'  => $pos++,
                'visible'   => true,
                'variation' => true,
                'options'   => array_values(array_unique(array_map('strval', $options))),
              ];
            }
            if (!empty($attrs)) {
              return $attrs;
            }
          }
        }
      }
    } catch (\Throwable $e) {
      // Resolver vorhanden, aber lieferte Fehler → Ignorieren, Fallback greift.
      Log::warning('WooAttributeResolver usage failed, falling back to DB aggregation', [
        'product_id' => $product->id,
        'message'    => $e->getMessage(),
      ]);
    }

    return [];
  }


  /**
   * Aggregiert alle Options-Werte je Attribut-Slug aus der DB-Struktur:
   * - product_attributes:   [id, product_id, slug (oder name)]
   * - product_attribute_values: [id, product_id, variation_id, attribute_id, value]
   *
   * Rückgabe: Map slug => unique options[]
   *   z. B. ['pa_size'=>['S','M'], 'pa_color'=>['Blue','Red']]
   *
   * @return array<string,array<int,string>>
   */
  private function collectVariantAttributes(\App\Models\Product $product): array
  {
    // Models vorausgesetzt (Passe die Namespaces an, falls abweichend)
    $PA  = \App\Models\ProductAttribute::query()
      ->where('product_id', $product->id)
      ->get(['id', 'product_id', 'slug', 'name']);

    if ($PA->isEmpty()) {
      Log::info('collectVariantAttributes: no product_attributes found', ['product_id' => $product->id]);
      return [];
    }

    // Map: attribute_id -> slug (oder name als Fallback)
    $idToSlug = [];
    foreach ($PA as $row) {
      $slug = $row->slug ?? null;
      if (!$slug || $slug === '') {
        // Fallback: name → in pa_* umwandeln, falls sinnvoll
        $slug = $this->normalizeAttrSlug((string) ($row->name ?? ''));
      }
      if (!$slug || $slug === '') {
        continue;
      }
      $idToSlug[(int) $row->id] = (string) $slug;
    }

    if (empty($idToSlug)) {
      Log::warning('collectVariantAttributes: attributes found but no slugs resolvable', ['product_id' => $product->id]);
      return [];
    }

    // Alle Varianten-IDs des Produkts
    $variantIds = \App\Models\ProductVariation::query()
      ->where('product_id', $product->id)
      ->pluck('id')
      ->all();

    // Alle Values für dieses Produkt – sowohl product-level als auch variation-level
    $PAV = \App\Models\ProductAttributeValue::query()
      ->where('product_id', $product->id)
      ->when(!empty($variantIds), fn($q) => $q->orWhereIn('variation_id', $variantIds))
      ->get(['attribute_id', 'value', 'variation_id']);

    if ($PAV->isEmpty()) {
      Log::info('collectVariantAttributes: no product_attribute_values found', ['product_id' => $product->id]);
      return [];
    }

    // Aggregation: slug => [values...]
    $acc = [];
    foreach ($PAV as $row) {
      $attrId = (int) ($row->attribute_id ?? 0);
      $val    = (string) ($row->value ?? '');

      if ($attrId <= 0 || $val === '') {
        continue;
      }
      $slug = $idToSlug[$attrId] ?? null;
      if (!$slug) {
        continue;
      }
      $acc[$slug][] = $val;
    }

    // Deduplizieren/aufbereiten
    $out = [];
    foreach ($acc as $slug => $vals) {
      $vals = array_values(array_unique(array_map('strval', array_filter($vals, fn($v) => $v !== null && $v !== ''))));
      if (!empty($vals)) {
        $out[(string) $slug] = $vals;
      }
    }

    Log::debug('collectVariantAttributes result', [
      'product_id' => $product->id,
      'attributes' => array_map(fn($v) => count($v), $out), // nur Anzahlen zur Übersicht
    ]);

    return $out;
  }

  /**
   * Vereinfacht Namen → Slug (pa_*) für bekannte Attribute.
   */
  private function normalizeAttrSlug(string $name): string
  {
    $n = trim(mb_strtolower($name));
    return match ($n) {
      'color', 'farbe', 'colour' => 'pa_color',
      'size', 'größe', 'groesse', 'gr' => 'pa_size',
      default => $n !== '' ? $n : '',
    };
  }
}
