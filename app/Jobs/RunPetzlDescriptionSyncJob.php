<?php

namespace App\Jobs;

use App\Models\Manufacturer;
use App\Models\PetzlDescriptionSyncRun;
use App\Models\Product;
use App\Services\Petzl\PetzlDescriptionImportService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunPetzlDescriptionSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $runId,
        public ?array $productIds = null,
    ) {}

    /**
     * Start the Petzl description sync and dispatch the matching product jobs.
     */
    public function handle(
        PetzlDescriptionImportService $importService,
    ): void
    {
        $run = PetzlDescriptionSyncRun::find($this->runId);

        if (! in_array($run->mode, ['missing', 'refresh'], true)) {
            throw new \InvalidArgumentException(
                "Unsupported Petzl description sync mode: {$run->mode}"
            );
        }

        $force = $run->mode === 'refresh';

        if (! $run) {
            Log::warning('Petzl description sync run not found.', [
                'run_id' => $this->runId,
            ]);

            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $petzl = Manufacturer::query()
                ->where('manufacturer', 'Petzl')
                ->firstOrFail();

            $query = Product::query()
                ->where('manufacturer_id', $petzl->id)
                ->whereNotNull('original_product_name');

            if (! empty($this->productIds)) {
                $query->whereIn('id', $this->productIds);
            }

           $products = $query->get([
                'id',
                'original_product_name',
                'description_source',
                'petzl_description_hash',
            ]);

            $jobs = $products
                ->filter(
                    fn (Product $product) =>
                        filled($product->original_product_name)
                        && $importService->shouldImport($product, $force)
                )
                ->map(fn (Product $product) => new SyncPetzlDescriptionJob(
                    productId: $product->id,
                    productName: $product->original_product_name,
                    force: $force,
                ))  
                ->values()
                ->all();

            if ($jobs === []) {
                $run->update([
                    'status' => 'done',
                    'finished_at' => now(),
                ]);

                return;
            }

            $batch = Bus::batch($jobs)
                ->name('Petzl description sync: ' . $run->id)
                ->onQueue('imports')
                ->allowFailures()
                ->finally(function (Batch $batch) use ($run): void {
                    $run->update([
                        'status' => 'done',
                        'finished_at' => now(),
                    ]);
                })
                ->dispatch();

            $run->update([
                'batch_id' => $batch->id,
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }
}