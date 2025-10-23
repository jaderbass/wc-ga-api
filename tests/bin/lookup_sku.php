<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Shop;
use App\Services\Woo\WooClient;

$sku = $argv[1] ?? null;
if (!$sku) {
  exit("Usage: php -f tests/bin/lookup_sku.php <SKU>\n");
}

$shop = Shop::firstOrFail();
$woo  = new WooClient($shop);

// 1) Exakte Suche (inkl. Trash)
$res = $woo->get('products', ['sku' => $sku, 'status' => 'any', 'per_page' => 10]);
if (is_array($res) && count($res)) {
  foreach ($res as $p) {
    printf("FOUND product id=%d type=%s name=%s\n", $p['id'], $p['type'], $p['name']);
  }
  exit(0);
}

// 2) Fallback: Volltext
$res2 = $woo->get('products', ['search' => $sku, 'status' => 'any', 'per_page' => 10]);
if (is_array($res2) && count($res2)) {
  foreach ($res2 as $p) {
    printf("CANDIDATE product id=%d type=%s name=%s sku=%s\n", $p['id'], $p['type'], $p['name'], $p['sku'] ?? '');
  }
  exit(0);
}

echo "No product found for SKU {$sku}\n";
