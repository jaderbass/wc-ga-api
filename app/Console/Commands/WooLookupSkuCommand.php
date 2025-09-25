<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooLookupSkuCommand
 *
 * Findet heraus, wo eine SKU in WooCommerce belegt ist:
 * - Prüft zuerst Produkte: GET /products?sku={sku}
 * - Prüft danach Varianten global, indem alle Produkte paginiert und deren Variationen durchsucht werden
 *   (Woo REST hat keinen direkten /variations?sku= Filter).
 *
 * Usage:
 *   php artisan woo:lookup:sku C071AA01
 *   php artisan woo:lookup:sku C071AA01 --per-page=50 --sleep=200
 *   php artisan woo:lookup:sku C071AA01 --limit=1000   # maximale Produktanzahl, die durchsucht wird
 *
 * Hinweise:
 * - Das ist ein Diagnose-Tool; es macht viele Requests (paginiert). Nutze --limit, wenn dein Katalog groß ist.
 * - Respektiert einfache Rate-Limits via --sleep (Millisekunden zwischen Requests).
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class WooLookupSkuCommand extends Command
{
  protected $signature = 'woo:lookup:sku
        {sku : The SKU to look up}
        {--per-page=100 : Page size for products/variations}
        {--sleep=100 : Sleep in milliseconds between HTTP requests}
        {--limit=0 : Max number of products to scan for variations (0 = no limit)}';

  protected $description = 'Diagnose where a SKU exists in WooCommerce (product or variation, across the catalog).';

  public function handle(): int
  {
    $sku     = (string) $this->argument('sku');
    $perPage = max(1, (int) $this->option('per-page'));
    $sleepMs = max(0, (int) $this->option('sleep'));
    $limit   = max(0, (int) $this->option('limit'));

    $base = rtrim((string) config('woo.api.base_url'), '/');
    $ver  = (string) config('woo.default_api_version', 'wc/v3');
    $key  = (string) config('woo.api.key');
    $sec  = (string) config('woo.api.secret');

    if (!$base || !$key || !$sec) {
      $this->error('Missing Woo API config (base_url/key/secret).');
      return self::INVALID;
    }

    $http = Http::baseUrl($base . '/wp-json/' . $ver)
      ->withBasicAuth($key, $sec)
      ->acceptJson()
      ->asJson()
      ->retry(2, 250);

    $this->info("Looking up SKU '{$sku}' on {$base} ({$ver}) …");

    // 1) Direkter Treffer als Produkt?
    $resp = $http->get('/products', ['sku' => $sku, 'per_page' => 1]);
    if ($resp->successful()) {
      $items = $resp->json() ?? [];
      if (is_array($items) && count($items) > 0) {
        $prod = $items[0];
        $this->line('');
        $this->components->twoColumnDetail('Found as PRODUCT', "ID {$prod['id']} · name \"{$prod['name']}\" · status {$prod['status']}");
        $this->line('→ Lösung: dieses Produkt updaten statt neu anlegen ODER SKU ändern.');
        return self::SUCCESS;
      }
    } else {
      $this->warn("Products lookup failed: HTTP {$resp->status()}");
    }

    // 2) Suche in Variationen (global: alle Produkte scannen, dann je Produkt Variationen)
    $this->line('');
    $this->info('Not found as product. Scanning VARIATIONS across products …');

    $page       = 1;
    $scanned    = 0;
    $foundRows  = [];

    while (true) {
      $resp = $http->get('/products', ['per_page' => $perPage, 'page' => $page]);
      if ($sleepMs > 0) usleep($sleepMs * 1000);

      if ($resp->failed()) {
        $this->warn("Products page {$page} failed: HTTP {$resp->status()}");
        break;
      }

      $products = $resp->json() ?? [];
      if (empty($products)) {
        break;
      }

      foreach ($products as $p) {
        $productId = (int) ($p['id'] ?? 0);
        $type      = (string) ($p['type'] ?? '');

        // Nur variable/variantentragende Produkte sind interessant
        if ($type !== 'variable') {
          $scanned++;
          if ($limit > 0 && $scanned >= $limit) break 2;
          continue;
        }

        // Variationen des Produkts laden (paginiert)
        $vPage = 1;
        while (true) {
          $vResp = $http->get("/products/{$productId}/variations", ['per_page' => $perPage, 'page' => $vPage]);
          if ($sleepMs > 0) usleep($sleepMs * 1000);

          if ($vResp->failed()) {
            $this->warn("Variations of product {$productId} page {$vPage} failed: HTTP {$vResp->status()}");
            break;
          }

          $vars = $vResp->json() ?? [];
          if (empty($vars)) {
            break;
          }

          foreach ($vars as $v) {
            if (($v['sku'] ?? null) === $sku) {
              $foundRows[] = [
                'product_id'   => $productId,
                'product_name' => (string) ($p['name'] ?? ''),
                'variation_id' => (int) ($v['id'] ?? 0),
                'status'       => (string) ($v['status'] ?? ''),
              ];
            }
          }

          // Pagination Ende?
          $totalPages = (int) ($vResp->header('X-WP-TotalPages') ?? 0);
          if ($totalPages > 0 && $vPage >= $totalPages) {
            break;
          }
          // Sonst weiter
          $vPage++;
        }

        $scanned++;
        if ($limit > 0 && $scanned >= $limit) break 2;
      }

      // Pagination Ende?
      $totalPages = (int) ($resp->header('X-WP-TotalPages') ?? 0);
      if ($totalPages > 0 && $page >= $totalPages) {
        break;
      }
      $page++;
    }

    if (!empty($foundRows)) {
      $this->line('');
      $this->components->twoColumnDetail('Found as VARIATION (count)', (string) count($foundRows));
      $this->table(['Product ID', 'Product Name', 'Variation ID', 'Status'], $foundRows);
      $this->line('→ Lösung: SKU ist bereits als Variation vergeben. SKU ändern ODER richtige Parent/Variante updaten.');
      Log::info('woo:lookup:sku result', ['sku' => $sku, 'matches' => $foundRows]);
      return self::SUCCESS;
    }

    $this->line('');
    $this->info('No product or variation found using this SKU.');
    return self::SUCCESS;
  }
}
