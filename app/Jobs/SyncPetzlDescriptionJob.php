<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Petzl\PetzlDescriptionImportService;
use App\Services\Petzl\PetzlProductUrlResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class SyncPetzlDescriptionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $productId,
        public string $productName,
        public bool $force = false,
        public ?string $sourceCategory = null,
        public ?string $sourceSubcategory = null,
    ) {}

    public function handle(
        PetzlProductUrlResolver $resolver,
        PetzlDescriptionImportService $importService,
    ): void {
        $product = Product::query()
            ->whereKey($this->productId)
            ->first();

        if (! $product) {
            return;
        }

        if (! $importService->shouldImport($product, $this->force)) {
            return;
        }

        try {
            $url = $resolver->resolveByProductName(
                $this->productName,
                $this->sourceCategory,
                $this->sourceSubcategory,
            );

            $importService->importFromUrl($product, $url);
        } catch (\Throwable $exception) {
            Log::warning('Petzl description sync failed.', [
                'product_id' => $this->productId,
                'product_name' => $this->productName,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
