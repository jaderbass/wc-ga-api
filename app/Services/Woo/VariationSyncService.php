<?php

namespace App\Services\Woo;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use function config;

/**
 * VariationSyncService
 *
 * Synchronisiert Variationen eines gegebenen Woo-Parent-Produkts:
 * - Baut Payloads via VariationPayloadBuilder (SKU, Attributes, Meta: EAN/GTIN/MPN; ohne Preise).
 * - Ermittelt, ob eine Variation bereits existiert (Match über SKU),
 *   und entscheidet dadurch zwischen Create (POST) und Update (PUT).
 * - Robuste Fehlerbehandlung (insb. 400 product_invalid_sku → sauber loggen, Lauf geht weiter).
 * - Respektiert einfache Rate-Limits aus config('woo.rate_limit').
 *
 * Voraussetzungen:
 * - $product->woo_product_id ist gesetzt (Woo-Parent existiert).
 * - $product->variations() liefert die lokalen Varianten (mit Spalten laut config mappings).
 *
 * Rückgabestruktur:
 *  [
 *    'created' => int,
 *    'updated' => int,
 *    'skipped' => int,
 *    'errors'  => int,
 *    'details' => array<int, array{
 *        variation_id?: int|string|null,
 *        sku?: string|null,
 *        action: 'created'|'updated'|'skipped'|'error',
 *        remote_id?: int|null,
 *        status?: int|null,
 *        error?: string|null
 *    }>
 *  ]
 *
 * @author  JAderBass
 * @since   2025-09-25
 */
class VariationSyncService
{
  public function __construct(
    protected VariationPayloadBuilder $builder
  ) {}

  /**
   * Synchronisiert alle Variationen für ein Parent-Produkt.
   *
   * @param  Product $product
   * @param  bool    $failHard   Bei HTTP-Fehlern Exception werfen?
   * @return array<string, mixed>
   */
  public function syncProduct(Product $product, bool $failHard = false): array
  {
    $parentId = (int) ($product->getAttribute('woo_product_id') ?? 0);
    if ($parentId <= 0) {
      Log::warning('VariationSyncService: cannot sync without woo_product_id', ['product_id' => $product->getKey()]);
      return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1, 'details' => [[
        'action' => 'error',
        'error'  => 'Missing woo_product_id on product',
      ]]];
    }

    $baseUrl = rtrim((string) config('woo.api.base_url'), '/');
    $version = (string) config('woo.default_api_version', 'wc/v3');
    $key     = (string) config('woo.api.key');
    $secret  = (string) config('woo.api.secret');

    $http = Http::baseUrl($baseUrl . '/wp-json/' . $version)
      ->withBasicAuth($key, $secret)
      ->acceptJson()
      ->asJson();

    $rpm   = (int) data_get(config('woo.rate_limit'), 'rpm', 100);
    $burst = (int) data_get(config('woo.rate_limit'), 'burst', 40);
    $sleepMicros = $this->calcSleepMicros($rpm, $burst);

    // 1) Payloads bauen
    $payloads = $this->builder->buildForProduct($product);
    if (empty($payloads)) {
      Log::info('VariationSyncService: no payloads built, nothing to sync', ['product_id' => $product->getKey()]);
      return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];
    }

    // 2) Remote-Varianten indizieren (SKU → Variation-ID) für schnelle Entscheidungen
    $remoteIndex = $this->indexRemoteVariationsBySku($http, $parentId, $sleepMicros);

    $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

    foreach ($payloads as $payload) {
      $sku = (string) ($payload['sku'] ?? '');
      $localVar = $this->findLocalVariationBySku($product, $sku); // nur für logging/ids

      if ($sku === '') {
        $summary['errors']++;
        $summary['details'][] = [
          'variation_id' => $localVar?->getAttribute('id'),
          'sku'          => null,
          'action'       => 'error',
          'error'        => 'Variation SKU missing in payload',
        ];
        continue;
      }

      try {
        if (isset($remoteIndex[$sku])) {
          // UPDATE
          $remoteVarId = (int) $remoteIndex[$sku];
          $resp = $http->put("/products/{$parentId}/variations/{$remoteVarId}", $payload);
          if ($sleepMicros > 0) usleep($sleepMicros);

          if ($resp->successful()) {
            $summary['updated']++;
            $summary['details'][] = [
              'variation_id' => $localVar?->getAttribute('id'),
              'sku'          => $sku,
              'action'       => 'updated',
              'remote_id'    => $remoteVarId,
              'status'       => $resp->status(),
            ];
          } else {
            $this->handleHttpFailure($resp, $summary, $localVar?->getAttribute('id'), $sku, $failHard);
          }
        } else {
          // CREATE
          $resp = $http->post("/products/{$parentId}/variations", $payload);
          if ($sleepMicros > 0) usleep($sleepMicros);

          if ($resp->successful()) {
            $remote = $resp->json();
            $remoteId = (int) data_get($remote, 'id');
            $summary['created']++;
            $summary['details'][] = [
              'variation_id' => $localVar?->getAttribute('id'),
              'sku'          => $sku,
              'action'       => 'created',
              'remote_id'    => $remoteId ?: null,
              'status'       => $resp->status(),
            ];
            // Index aktualisieren, damit spätere Duplikate im selben Lauf updaten
            if ($remoteId > 0) {
              $remoteIndex[$sku] = $remoteId;
            }
          } else {
            $this->handleHttpFailure($resp, $summary, $localVar?->getAttribute('id'), $sku, $failHard);
          }
        }
      } catch (\Throwable $e) {
        Log::error('VariationSyncService: exception during sync', [
          'product_id'   => $product->getKey(),
          'parent_id'    => $parentId,
          'variation_id' => $localVar?->getAttribute('id'),
          'sku'          => $sku,
          'error'        => $e->getMessage(),
        ]);
        if ($failHard) {
          throw $e;
        }
        $summary['errors']++;
        $summary['details'][] = [
          'variation_id' => $localVar?->getAttribute('id'),
          'sku'          => $sku,
          'action'       => 'error',
          'error'        => $e->getMessage(),
        ];
      }
    }

    Log::info('VariationSyncService: Produkt-Varianten synchronisiert', [
      'product_id'     => $product->getKey(),
      'woo_product_id' => $parentId,
      'summary'        => $summary,
    ]);

    return $summary;
  }

  /**
   * Erstellt ein SKU→VariationID-Index aus Woo (paginiert).
   * Woo erlaubt keinen direkten sku-Filter auf /variations, daher paginieren.
   *
   * @param  \Illuminate\Http\Client\PendingRequest $http
   * @param  int $parentId
   * @param  int $sleepMicros
   * @return array<string,int>  sku => variation_id
   */
  protected function indexRemoteVariationsBySku($http, int $parentId, int $sleepMicros): array
  {
    $index = [];
    $page = 1;
    $perPage = (int) config('woo.sync.variations.per_page', 100);

    while (true) {
      $resp = $http->get("/products/{$parentId}/variations", [
        'per_page' => $perPage,
        'page'     => $page,
      ]);
      if ($sleepMicros > 0) usleep($sleepMicros);

      if ($resp->failed()) {
        Log::warning('VariationSyncService: failed to list remote variations', [
          'parent_id' => $parentId,
          'status'    => $resp->status(),
          'body'      => Str::limit((string) $resp->body(), 500),
        ]);
        break;
      }

      $items = $resp->json() ?? [];
      if (empty($items)) {
        break;
      }

      foreach ($items as $v) {
        $sku = (string) data_get($v, 'sku', '');
        $id  = (int) data_get($v, 'id', 0);
        if ($sku !== '' && $id > 0) {
          $index[$sku] = $id;
        }
      }

      $totalPages = (int) ($resp->header('X-WP-TotalPages') ?? 0);
      if ($totalPages > 0 && $page >= $totalPages) {
        break;
      }
      $page++;
    }

    return $index;
  }

  /**
   * Behandelt fehlgeschlagene HTTP-Antworten (400..).
   * - Spezieller Fall: 400 product_invalid_sku -> klarere Meldung, nicht den gesamten Lauf abbrechen.
   *
   * @param  \Illuminate\Http\Client\Response $resp
   * @param  array<string,mixed>              $summary (by-ref)
   * @param  int|string|null                  $localVarId
   * @param  string                           $sku
   * @param  bool                             $failHard
   * @return void
   */
  protected function handleHttpFailure($resp, array &$summary, $localVarId, string $sku, bool $failHard): void
  {
    $status = $resp->status();
    $body   = $resp->json();
    $code   = (string) data_get($body, 'code', '');
    $msg    = (string) data_get($body, 'message', '');
    $data   = data_get($body, 'data', []);

    // Spezialfall: doppelte/ungültige SKU
    if ($status === 400 && $code === 'product_invalid_sku') {
      $hint = 'Woo reported duplicate/invalid SKU. Use "php artisan woo:lookup:sku ' . $sku . '" to locate collisions (product/variation), remove or rename in Woo, then retry.';
      Log::warning('VariationSyncService: product_invalid_sku', [
        'variation_id' => $localVarId,
        'sku'          => $sku,
        'status'       => $status,
        'code'         => $code,
        'message'      => $msg,
        'data'         => $data,
        'hint'         => $hint,
      ]);

      $summary['errors']++;
      $summary['details'][] = [
        'variation_id' => $localVarId,
        'sku'          => $sku,
        'action'       => 'error',
        'status'       => $status,
        'error'        => "{$code}: {$msg}",
      ];

      if ($failHard) {
        throw new \RuntimeException("Woo API error {$status} {$code}: {$msg}");
      }
      return;
    }

    // Generischer Fehler
    Log::error('VariationSyncService: HTTP failure', [
      'variation_id' => $localVarId,
      'sku'          => $sku,
      'status'       => $status,
      'body'         => is_scalar($body) ? $body : json_encode($body),
    ]);

    $summary['errors']++;
    $summary['details'][] = [
      'variation_id' => $localVarId,
      'sku'          => $sku,
      'action'       => 'error',
      'status'       => $status,
      'error'        => is_string($body) ? $body : ($body ? json_encode($body) : 'HTTP error ' . $status),
    ];

    if ($failHard) {
      throw new \RuntimeException('Woo API error: ' . (is_string($body) ? $body : json_encode($body)));
    }
  }

  /**
   * Findet eine lokale Variation via $product->variations() anhand SKU (für Logging).
   *
   * @param  Product $product
   * @param  string  $sku
   * @return \Illuminate\Database\Eloquent\Model|null
   */
  protected function findLocalVariationBySku(Product $product, string $sku)
  {
    if (!method_exists($product, 'variations') || $sku === '') {
      return null;
    }
    return $product->variations()->where(function ($q) use ($sku) {
      $col = config('woo.mapping.identifiers.variation.sku', 'sku');
      $q->where($col, '=', $sku);
    })->first();
  }

  /**
   * Einfache Sleep-Berechnung für Rate-Limits (rpm/burst).
   * Sehr konservativ: verteilt Requests gleichmäßig.
   */
  protected function calcSleepMicros(int $rpm, int $burst): int
  {
    // 60s / rpm → Sekunden/Request
    if ($rpm <= 0) return 0;
    $secPerReq = 60 / max(1, $rpm);
    // bei Burst etwas kulanter sein
    $factor = $burst > 0 ? 0.8 : 1.0;
    return (int) max(0, $secPerReq * $factor * 1_000_000);
  }
}
