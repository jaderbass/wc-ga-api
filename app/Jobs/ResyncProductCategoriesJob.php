<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Categories\ProductCategorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronisiert Produktkategorien anhand der aktuellen Regeln neu.
 */
class ResyncProductCategoriesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param int|null $productId Nur ein bestimmtes Produkt synchronisieren
     * @param int|null $manufacturerId Nur Produkte eines Herstellers synchronisieren
     * @param int $chunkSize Chunk-Größe für die Verarbeitung
     */
    public function __construct(
        protected ?int $productId = null,
        protected ?int $manufacturerId = null,
        protected int $chunkSize = 200,
    ) {}

    /**
     * Führt den Job aus.
     */
    public function handle(ProductCategorySyncService $syncService): void
    {
        $query = Product::query()->select(['id', 'manufacturer_id', 'product_name']);

        if ($this->productId !== null) {
            $query->whereKey($this->productId);
        }

        if ($this->manufacturerId !== null) {
            $query->where('manufacturer_id', $this->manufacturerId);
        }

        $query
            ->orderBy('id')
            ->chunkById($this->chunkSize, function ($products) use ($syncService): void {
                foreach ($products as $product) {
                    $syncService->sync($product);
                }
            });
    }
}
