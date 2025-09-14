<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\WooProductService;
use Illuminate\Console\Command;

class WooSyncProduct extends Command
{
    protected $signature = 'woo:sync:product 
                            {productId : ID des Produkts} 
                            {--shop= : Shop-ID oder Name (optional)} 
                            {--only-changed : Nur senden, wenn sich die Payload geändert hat}
                            {--dry-run : Nur Diff/Preview anzeigen, keine API-Calls}';

    protected $description = 'Synchronisiere ein einzelnes Produkt zu WooCommerce.';

    public function handle(WooProductService $service): int
    {
        $productId = (int) $this->argument('productId');
        $shop = $this->resolveShop((string) $this->option('shop'));
        $product = Product::findOrFail($productId);

        $this->info("Sync product #{$product->id} to shop '{$shop->name}'");

        $result = $service->upsertProduct(
            $product,
            $shop,
            (bool) $this->option('dry-run'),
            (bool) $this->option('only-changed')
        );

        $this->line('Result: ' . json_encode($result));

        return self::SUCCESS;
    }

    protected function resolveShop(?string $shopOpt): Shop
    {
        $query = Shop::query();
        if ($shopOpt) {
            return ctype_digit($shopOpt)
                ? $query->where('id', (int) $shopOpt)->firstOrFail()
                : $query->where('name', $shopOpt)->firstOrFail();
        }
        return $query->where('is_default', true)->firstOrFail();
    }
}
