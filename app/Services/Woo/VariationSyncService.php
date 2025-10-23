<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VariationSyncService
 *
 * Synchronisiert Produkt-Varianten (Outbound) zu WooCommerce:
 * - Lädt existierende Woo-Varianten eines Produkts (paginiert)
 * - Mappt per SKU auf bestehende Einträge
 * - Erstellt neue Varianten oder aktualisiert bestehende
 *
 * Endpunkte (Woo REST):
 * - GET  {base}/wp-json/{ver}/products/{productId}/variations
 * - POST {base}/wp-json/{ver}/products/{productId}/variations
 * - PUT  {base}/wp-json/{ver}/products/{productId}/variations/{variationId}
 *
 * Konfiguration (siehe config/woo.php):
 * - 'default_api_version' (z.B. 'wc/v3')
 * - 'rate_limit.rpm'      (Requests pro Minute) -> einfache Sleep-Strategie
 * - 'sync.variations.per_page' (optional, Default 100)
 * - 'api.base_url', 'api.key', 'api.secret' (werden im nächsten Schritt ergänzt)
 *
 * Hinweise:
 * - Preise werden NICHT synchronisiert (Projektvorgabe).
 * - Produkt benötigt eine remote ID in products.woo_product_id.
 *
 * @author  JAderBass
 * @since   2025-09-19
 */
class VariationSyncService
{
  protected VariationPayloadBuilder $builder;
  protected PendingRequest $http;
  protected string $baseUrl;
  protected string $apiVersion;
  protected int $perPage;
  protected int $sleepMsPerRequest;

  public function __construct(VariationPayloadBuilder $builder)
  {
    $this->builder    = $builder;
    $this->baseUrl    = rtrim((string) config('woo.api.base_url'), '/');
    $this->apiVersion = (string) config('woo.default_api_version', 'wc/v3');
    $this->perPage    = (int) config('woo.sync.variations.per_page', 100);

    $rpm = (int) config('woo.rate_limit.rpm', 100);
    $this->sleepMsPerRequest = $rpm > 0 ? (int) floor(60000 / $rpm) : 0;

    $this->http = Http::baseUrl($this->baseUrl . '/wp-json/' . $this->apiVersion)
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
   * Synchronisiert alle Varianten eines Produkts.
   *
   * @param  Product $product
   * @param  bool    $failHard  Wenn true, Exceptions bei HTTP-Fehlern.
   * @return array{created:int,updated:int,skipped:int,errors:int,details:array<int,array<string,mixed>>}
   */
  public function syncProduct(Product $product, bool $failHard = false): array
  {
    if (empty($product->woo_product_id)) {
      Log::warning('VariationSyncService: Produkt ohne woo_product_id, übersprungen', [
        'product_id' => $product->id,
      ]);
      return ['created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0, 'details' => []];
    }

    $variations = $product->variations()->get();
    if ($variations->isEmpty()) {
      Log::info('VariationSyncService: Keine lokalen Varianten vorhanden', [
        'product_id' => $product->id,
      ]);
      return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];
    }

    // Remote-Varianten indexieren (per SKU)
    $remoteBySku = $this->fetchRemoteVariationsBySku((int) $product->woo_product_id);

    $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

    /** @var ProductVariation $variation */
    foreach ($variations as $variation) {
      $payload = $this->builder->buildForVariation($product, $variation);

      $sku = $payload['sku'] ?? null;
      if (empty($sku)) {
        Log::warning('VariationSyncService: Variante ohne SKU, übersprungen', [
          'product_id'   => $product->id,
          'variation_id' => $variation->id,
        ]);
        $result['skipped']++;
        $result['details'][] = [
          'variation_id' => $variation->id,
          'action'       => 'skipped',
          'reason'       => 'missing-sku',
        ];
        continue;
      }

      try {
        if (isset($remoteBySku[$sku])) {
          $remoteVarId = (int) $remoteBySku[$sku]['id'];
          $resp = $this->putVariation((int) $product->woo_product_id, $remoteVarId, $payload);
          $this->rateLimitNap();
          $result['updated']++;
          $result['details'][] = [
            'variation_id' => $variation->id,
            'sku'          => $sku,
            'action'       => 'updated',
            'remote_id'    => $remoteVarId,
            'status'       => $resp->status(),
          ];
        } else {
          $resp = $this->postVariation((int) $product->woo_product_id, $payload);
          $this->rateLimitNap();
          $remote = $resp->json();
          $result['created']++;
          $result['details'][] = [
            'variation_id' => $variation->id,
            'sku'          => $sku,
            'action'       => 'created',
            'remote_id'    => $remote['id'] ?? null,
            'status'       => $resp->status(),
          ];
        }
      } catch (\Throwable $e) {
        Log::error('VariationSyncService: Fehler beim Sync einer Variante', [
          'product_id'   => $product->id,
          'variation_id' => $variation->id,
          'sku'          => $sku,
          'message'      => $e->getMessage(),
        ]);
        $result['errors']++;
        $result['details'][] = [
          'variation_id' => $variation->id,
          'sku'          => $sku,
          'action'       => 'error',
          'error'        => $e->getMessage(),
        ];

        if ($failHard) {
          throw $e;
        }
      }
    }

    Log::info('VariationSyncService: Produkt-Varianten synchronisiert', [
      'product_id'     => $product->id,
      'woo_product_id' => $product->woo_product_id,
      'summary'        => $result,
    ]);

    return $result;
  }

  /**
   * Synchronisiert Varianten für mehrere Produkte.
   *
   * @param  Collection<int,Product>|array<int,Product> $products
   * @param  bool $failHard
   * @return array<string,mixed>
   */
  public function syncMany(Collection|array $products, bool $failHard = false): array
  {
    $summary = [
      'products' => 0,
      'created' => 0,
      'updated' => 0,
      'skipped' => 0,
      'errors' => 0,
      'items' => [],
    ];

    foreach ($products as $product) {
      $summary['products']++;
      $res = $this->syncProduct($product, $failHard);
      $summary['created'] += $res['created'];
      $summary['updated'] += $res['updated'];
      $summary['skipped'] += $res['skipped'];
      $summary['errors']  += $res['errors'];
      $summary['items'][]  = [
        'product_id'     => $product->id,
        'woo_product_id' => $product->woo_product_id,
        'result'         => $res,
      ];
    }

    Log::info('VariationSyncService: Bulk-Sync abgeschlossen', ['summary' => $summary]);

    return $summary;
  }

  /**
   * Lädt alle Remote-Varianten eines Woo-Produkts und indiziert sie per SKU.
   *
   * @param  int $wooProductId
   * @return array<string,array{id:int,sku:string}>
   */
  protected function fetchRemoteVariationsBySku(int $wooProductId): array
  {
    $page = 1;
    $map  = [];

    do {
      $resp = $this->http->get("/products/{$wooProductId}/variations", [
        'per_page' => $this->perPage,
        'page'     => $page,
      ]);

      $this->throwIfFailed($resp, "GET variations (page {$page})");

      $items = $resp->json() ?? [];
      foreach ($items as $item) {
        $sku = $item['sku'] ?? null;
        if (!empty($sku)) {
          $map[$sku] = ['id' => $item['id'], 'sku' => $sku];
        }
      }

      $total   = (int) ($resp->header('X-WP-Total') ?? 0);
      $fetched = $page * $this->perPage;
      $page++;
      $this->rateLimitNap();
    } while ($fetched < $total);

    Log::debug('VariationSyncService: Remote-Variationen geladen', [
      'woo_product_id' => $wooProductId,
      'count'          => count($map),
    ]);

    return $map;
  }

  /**
   * POST: Neue Variante anlegen.
   *
   * @param  int   $wooProductId
   * @param  array $payload
   * @return Response
   */
  protected function postVariation(int $wooProductId, array $payload): Response
  {
    $url  = "/products/{$wooProductId}/variations";
    $resp = $this->http->post($url, $payload);
    $this->throwIfFailed($resp, 'POST variation');

    Log::debug('VariationSyncService: Variante erstellt', [
      'woo_product_id' => $wooProductId,
      'sku'            => $payload['sku'] ?? null,
      'status'         => $resp->status(),
    ]);

    return $resp;
  }

  /**
   * PUT: Variante aktualisieren.
   *
   * @param  int   $wooProductId
   * @param  int   $wooVariationId
   * @param  array $payload
   * @return Response
   */
  protected function putVariation(int $wooProductId, int $wooVariationId, array $payload): Response
  {
    $url  = "/products/{$wooProductId}/variations/{$wooVariationId}";
    $resp = $this->http->put($url, $payload);
    $this->throwIfFailed($resp, 'PUT variation');

    Log::debug('VariationSyncService: Variante aktualisiert', [
      'woo_product_id'  => $wooProductId,
      'woo_variation_id' => $wooVariationId,
      'sku'             => $payload['sku'] ?? null,
      'status'          => $resp->status(),
    ]);

    return $resp;
  }

  /**
   * Wirft bei HTTP-Fehlern eine Exception mit kurzer Kontextinfo.
   *
   * @param  Response $resp
   * @param  string   $action
   * @return void
   */
  protected function throwIfFailed(Response $resp, string $action): void
  {
    if ($resp->successful()) {
      return;
    }

    $body = $resp->json();
    $msg  = is_array($body) ? json_encode($body) : (string) $resp->body();

    Log::error('VariationSyncService: HTTP-Fehler', [
      'action' => $action,
      'status' => $resp->status(),
      'body'   => $msg,
    ]);

    throw new \RuntimeException("Woo API {$action} failed: HTTP {$resp->status()} {$msg}");
  }

  /**
   * Einfache Sleep-Strategie basierend auf rate_limit.rpm.
   *
   * @return void
   */
  protected function rateLimitNap(): void
  {
    if ($this->sleepMsPerRequest > 0) {
      usleep($this->sleepMsPerRequest * 1000);
    }
  }
}
