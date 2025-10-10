<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int)($argv[1] ?? 0);
if ($id <= 0) {
  exit("Bitte Produkt-ID angeben\n");
}

DB::table('products')->where('id', $id)->update(['woo_product_id' => null]);
echo "woo_product_id für Produkt $id auf NULL gesetzt.\n";
