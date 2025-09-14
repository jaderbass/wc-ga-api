<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\WooProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchProductSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $productId,
        protected int $shopId
    ) {}

    public function handle(WooProductService $service): void
    {
        $product = Product::find($this->productId);
        $shop = Shop::find($this->shopId);

        if (! $product || ! $shop) {
            Log::warning('DispatchProductSync skipped: product/shop missing', [
                'productId' => $this->productId,
                'shopId' => $this->shopId,
            ]);
            return;
        }

        $service->upsertProduct($product, $shop);
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }
}
