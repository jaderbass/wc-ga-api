<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$affected = DB::table('products')->whereNotNull('woo_product_id')->update(['woo_product_id' => null]);
echo "woo_product_id für {$affected} Produkte auf NULL gesetzt.\n";
