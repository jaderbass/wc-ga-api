<?php

/**
 * Usage:
 *   php -f tests/bin/find_sku_anywhere.php C071BA00
 */

use App\Models\Shop;
use App\Services\Woo\WooClient;
use GuzzleHttp\Exception\ConnectException;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sku = $argv[1] ?? null;
if (!$sku) {
  fwrite(STDERR, "Usage: php -f tests/bin/find_sku_anywhere.php <SKU>\n");
  exit(2);
}

$shop = Shop::query()->firstOrFail();
$woo  = new WooClient($shop);

// Simple Retry-Wrapper für sporadische DNS-Hiccups
$fetch = function (string $path, array $params = []) use ($woo) {
  $tries = 0;
  $delayUs = 200000; // 200ms
  while (true) {
    try {
      return $woo->get($path, $params);
    } catch (\RuntimeException $e) {
      $msg = $e->getMessage();
      if ((str_contains($msg, 'cURL error 6') || str_contains($msg, 'Could not resolve host'))
        && ++$tries <= 4
      ) {
        usleep($delayUs);
        $delayUs *= 2;
        continue;
      }
      throw $e;
    } catch (ConnectException $e) {
      if (++$tries <= 4) {
        usleep($delayUs);
        $delayUs *= 2;
        continue;
      }
      throw $e;
    }
  }
};

$hasItems = static fn($x): bool => is_array($x) && !empty($x);

// 1) Exakte Produktsuche (inkl. Papierkorb)
$prods = $fetch('products', ['sku' => $sku, 'status' => 'any', 'per_page' => 10]);
if ($hasItems($prods)) {
  foreach ($prods as $p) {
    printf("FOUND product  id=%d  type=%s  name=\"%s\"\n", $p['id'], $p['type'] ?? '?', $p['name'] ?? '');
  }
  exit(0);
}

// 2) Fallback Volltext
$cands = $fetch('products', ['search' => $sku, 'status' => 'any', 'per_page' => 10]);
if ($hasItems($cands)) {
  foreach ($cands as $p) {
    printf("CANDIDATE product  id=%d  type=%s  name=\"%s\" sku=\"%s\"\n", $p['id'], $p['type'] ?? '?', $p['name'] ?? '', $p['sku'] ?? '');
  }
}

// 3) Variations gezielt per SKU prüfen (pro Parent nur 1 Request)
$maxPages = 50;
$perPage = 50;
for ($page = 1; $page <= $maxPages; $page++) {
  $parents = $fetch('products', ['type' => 'variable', 'status' => 'any', 'per_page' => $perPage, 'page' => $page]);
  if (!$hasItems($parents)) break;

  foreach ($parents as $parent) {
    $pid = (int)($parent['id'] ?? 0);
    $pname = (string)($parent['name'] ?? '');

    // Variations-Endpoint mit sku-Filter (entlastet stark!)
    $vars = $fetch("products/{$pid}/variations", ['sku' => $sku, 'status' => 'any', 'per_page' => 10, 'page' => 1]);
    if ($hasItems($vars)) {
      $v = $vars[0];
      printf("FOUND variation id=%d parent_id=%d parent_name=\"%s\"\n", (int)$v['id'], $pid, $pname);
      exit(0);
    }
  }
}

echo "NOT FOUND\n";
exit(1);
