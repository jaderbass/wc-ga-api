<?php

namespace App\Services\Woo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooProductLookupService
 *
 * Bietet Hilfsmethoden, um Produkte in WooCommerce per SKU oder ID abzufragen.
 * Nutzt die REST-API (wc/v3) und Authentifizierung aus config/woo.php.
 *
 * Einsatz:
 * - Vor einem Create (POST) prüfen, ob ein Produkt mit SKU bereits existiert.
 * - Hilft Duplicate-SKU-Fehler zu vermeiden.
 *
 * Beispiel:
 *   $lookup = app(WooProductLookupService::class);
 *   $id = $lookup->findProductIdBySku('C071AA00');
 *   if ($id !== null) {
 *       // update statt create
 *   }
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class WooProductLookupService
{
  protected string $baseUrl;
  protected string $apiVersion;
  protected string $key;
  protected string $secret;

  public function __construct()
  {
    $this->baseUrl    = rtrim((string) config('woo.api.base_url'), '/');
    $this->apiVersion = (string) config('woo.default_api_version', 'wc/v3');
    $this->key        = (string) config('woo.api.key');
    $this->secret     = (string) config('woo.api.secret');
  }

  /**
   * Findet die Woo-Produkt-ID anhand einer SKU.
   *
   * @param  string $sku
   * @return int|null
   */
  public function findProductIdBySku(string $sku): ?int
  {
    $url = "{$this->baseUrl}/wp-json/{$this->apiVersion}/products";
    try {
      $resp = Http::withOptions([
        'curl' => [
          CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
          CURLOPT_DNS_CACHE_TIMEOUT => 60,
        ],
      ])
        ->withBasicAuth($this->key, $this->secret)
        ->acceptJson()
        ->asJson()
        ->retry(4, 200)
        ->get($url, [
          'sku'      => $sku,
          'status'   => 'any', // wichtig: findet auch Papierkorb-Einträge
          'per_page' => 10,
        ])
        ->throw();

      $items = $resp->json();


      if ($resp->failed()) {
        Log::warning('WooProductLookupService: request failed', [
          'sku' => $sku,
          'status' => $resp->status(),
          'body' => $resp->body(),
        ]);
        return null;
      }

      $items = $resp->json();
      if (is_array($items) && count($items) > 0) {
        $id = $items[0]['id'] ?? null;
        Log::info('WooProductLookupService: product found by SKU', [
          'sku' => $sku,
          'woo_id' => $id,
        ]);
        return $id ? (int) $id : null;
      }

      Log::debug('WooProductLookupService: no product found by SKU', ['sku' => $sku]);
      return null;
    } catch (\Throwable $e) {
      Log::error('WooProductLookupService: exception during lookup', [
        'sku' => $sku,
        'error' => $e->getMessage(),
      ]);
      return null;
    }
  }
}
