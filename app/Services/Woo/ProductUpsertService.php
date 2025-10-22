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

    // --- Mini-Patch: Name immer aus product_name ableiten, wenn nicht gesetzt ---
    // Hintergrund: Woo zeigt derzeit "Product #<id>", wenn 'name' fehlt.
    // Lösung: payload['name'] aus $product->product_name übernehmen.
    if (!isset($payload['name']) || $payload['name'] === null || $payload['name'] === '') {
      if (isset($product->product_name) && $product->product_name !== '') {
        $payload['name'] = $product->product_name;
      }
    }

    // Debug-Log, damit im Log eindeutig sichtbar ist, welcher Name zu Woo geht.
    // Achtung: Log-Ausgaben ohne Backslash (siehe Projektregel).
    Log::debug('ProductUpsertService: resolved name for upsert', [
      'product_id'    => $product->id ?? null,
      'resolved_name' => $payload['name'] ?? null,
    ]);

    // --- Vereinheitlichte Upsert-Delegation + Normalisierung ---
    // Wir delegieren an die bestehenden HTTP-Helper, damit alle Requests
    // zentral über $this->http laufen, und normalisieren anschließend die Response.
    $remoteId = (int) ($product->woo_product_id ?? 0);

    if ($remoteId > 0) {
      // UPDATE
      $res = $this->updateExisting($remoteId, $product, $payload, $failHard);
    } else {
      // CREATE
      $res = $this->createNew($product, $payload, $failHard);
    }

    // Einheitliches Response-Shape für Aufrufer (BulkAction, Orchestrator, etc.)
    return $this->normalizeUpsertResponse($res);
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
   * Aggregiert alle Options-Werte je Attribut-Slug auf Basis der echten DB-Struktur:
   * pv (product_variations) → piv (product_variation_attribute_value)
   * → pav (product_attribute_values) → pa (product_attributes)
   *
   * Rückgabe: Map slug => unique options[]
   *   z. B. ['pa_size'=>['S','M','L'], 'pa_color'=>['Blue','Red']]
   */
  private function collectVariantAttributes(\App\Models\Product $product): array
  {
    // Wir arbeiten bewusst mit Query Builder, um keine Relations vorauszusetzen.
    $rows = \Illuminate\Support\Facades\DB::table('product_variations as pv')
      ->join('product_variation_attribute_value as piv', 'piv.product_variation_id', '=', 'pv.id')
      ->join('product_attribute_values as pav', 'pav.id', '=', 'piv.product_attribute_value_id')
      ->join('product_attributes as pa', 'pa.id', '=', 'pav.attribute_id')
      ->where('pv.product_id', $product->id)
      ->select([
        'pa.slug as attr_slug',          // erwarteter Woo-Slug, idealerweise 'pa_*'
        'pav.value as option_value',     // sichtbarer Options-Text
        // 'pav.slug as option_slug',    // falls du Term-Slugs verwenden willst
      ])
      ->get();

    $acc = [];
    foreach ($rows as $r) {
      $slug = (string) ($r->attr_slug ?? '');
      $val  = (string) ($r->option_value ?? '');
      if ($slug === '' || $val === '') {
        continue;
      }
      // Optional: Slug normalisieren (nur wenn nötig)
      // if (!str_starts_with($slug, 'pa_')) { $slug = 'pa_' . $slug; }
      $acc[$slug][] = $val;
    }

    // Deduplizieren / leere entfernen
    $out = [];
    foreach ($acc as $slug => $vals) {
      $vals = array_values(array_unique(array_filter(array_map('strval', $vals), fn($v) => $v !== '')));
      if (!empty($vals)) {
        $out[$slug] = $vals;
      }
    }

    Log::debug('collectVariantAttributes: aggregated from piv/pav/pa', [
      'product_id' => $product->id,
      'attributes' => array_map(fn($v) => count($v), $out), // nur Anzahlen
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

  /**
   * Vereinheitlicht beliebige Service/HTTP-Responses in ein konsistentes Array.
   * Liefert immer: ['status'=>int, 'id'=>?int, 'remote_id'=>?int, 'action'=>?string, 'body'=>array]
   */
  private function normalizeUpsertResponse(mixed $res): array
  {
    $status = null;
    $id     = null;
    $action = null;
    $body   = null;

    if (is_array($res)) {
      // Häufige Muster aus unseren Services
      $status = $res['status'] ?? $res['code'] ?? null;
      $id     = $res['remote_id'] ?? $res['id'] ?? ($res['body']['id'] ?? null);
      $action = $res['action'] ?? null;
      $body   = $res['body'] ?? $res;
    } elseif (is_object($res)) {
      // HTTP-Wrapper oder stdClass
      if (method_exists($res, 'json')) {
        try {
          $body = $res->json();
        } catch (\Throwable $e) {
          $body = null;
        }
      } elseif (property_exists($res, 'body')) {
        $body = is_array($res->body) ? $res->body : json_decode((string) $res->body, true);
      } else {
        // stdClass → in Array kippen
        $body = json_decode(json_encode($res), true);
      }

      if (method_exists($res, 'status')) {
        $status = $res->status();
      } elseif (method_exists($res, 'getStatusCode')) {
        $status = $res->getStatusCode();
      } elseif (is_array($body) && isset($body['status'])) {
        $status = $body['status'];
      }

      $id     = $body['id'] ?? ($res->id ?? ($res->remote_id ?? null));
      $action = $body['action'] ?? ($res->action ?? null);
    }

    $normalized = [
      'status'    => (int) ($status ?? 0),
      'id'        => $id !== null ? (int) $id : null,
      'remote_id' => $id !== null ? (int) $id : null,
      'action'    => is_string($action) ? $action : null,
      'body'      => is_array($body) ? $body : (is_string($body) ? ['raw' => $body] : []),
    ];

    // Debug zur Kontrolle der Normalisierung
    Log::debug('ProductUpsertService: normalized upsert response', [
      'status'    => $normalized['status'],
      'remote_id' => $normalized['remote_id'],
      'action'    => $normalized['action'],
    ]);

    return $normalized;
  }
}
