<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\ProductNameContext;
use Illuminate\Console\Command;

class BackfillProductNamesCommand extends Command
{
    protected $signature = 'products:names:backfill {--manufacturerId=} {--dry-run}';
    protected $description = 'Backfill computed product names into products.product_name';

    public function handle(\App\Services\ProductNaming\ProductNameUpdater $updater): int
    {
        $manufacturerId = $this->option('manufacturerId');
        $dryRun = (bool) $this->option('dry-run');

        $q = Product::query()
            ->with(['manufacturer', 'variations.attributeValues.attribute']);

        if ($manufacturerId !== null && $manufacturerId !== '') {
            $q->where('manufacturer_id', (int) $manufacturerId);
        }

        $total = 0;
        $updated = 0;

        $q->chunkById(200, function ($products) use ($updater, $dryRun, &$total, &$updated) {
            foreach ($products as $product) {
                $total++;

                $result = $updater->compute($product);
                $calc = trim((string) $result->productName);
                $db = (string) ($product->product_name ?? '');

                if ($calc === '' || $calc === $db) {
                    continue;
                }

                if (! $dryRun) {
                    // original nur setzen, wenn noch leer
                    if (is_string($product->original_product_name) && trim($product->original_product_name) === '') {
                        $product->original_product_name = $db;
                        $product->saveQuietly();
                    } elseif ($product->original_product_name === null) {
                        $product->original_product_name = $db;
                        $product->saveQuietly();
                    }

                    $updater->update($product);
                }

                $updated++;
                $this->line("#{$product->id}: {$db}  =>  {$calc}");
            }
        });

        $this->info("Done. total={$total}, updated={$updated}, dryRun=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
