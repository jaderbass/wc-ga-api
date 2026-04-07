<?php

namespace App\Jobs;

use App\Models\CategoryResyncRun;
use App\Models\Product;
use App\Services\Categories\ProductCategorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

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
     * @param int|null $runId Zugehöriger Resync-Lauf
     */
    public function __construct(
        protected ?int $productId = null,
        protected ?int $manufacturerId = null,
        protected int $chunkSize = 200,
        protected ?int $runId = null,
    ) {}

    /**
     * Führt den Job aus.
     */
    public function handle(ProductCategorySyncService $syncService): void
    {
        $run = $this->runId ? CategoryResyncRun::find($this->runId) : null;

        $query = Product::query()->select(['id', 'manufacturer_id', 'product_name']);

        if ($this->productId !== null) {
            $query->whereKey($this->productId);
        }

        if ($this->manufacturerId !== null) {
            $query->where('manufacturer_id', $this->manufacturerId);
        }

        $total = (clone $query)->count();

        if ($run) {
            $run->update([
                'status' => 'running',
                'total' => $total,
                'processed' => 0,
                'started_at' => now(),
                'finished_at' => null,
                'message' => null,
            ]);
        }

        $processed = 0;

        try {
            $query
                ->orderBy('id')
                ->chunkById($this->chunkSize, function ($products) use ($syncService, $run, &$processed): void {
                    foreach ($products as $product) {
                        $syncService->sync($product);
                        $processed++;

                    if ($run) {
                        $run->update([
                            'processed' => $processed,
                        ]);
                    }
                    }
                });

            if ($run) {
                $run->update([
                    'status' => 'finished',
                    'processed' => $processed,
                    'finished_at' => now(),
                    'message' => 'Neuzuordnung abgeschlossen.',
                ]);
            }
        } catch (Throwable $e) {
            if ($run) {
                $run->update([
                    'status' => 'failed',
                    'processed' => $processed,
                    'finished_at' => now(),
                    'message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
