<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\ProductKind;
use App\Services\ProductNaming\ProductNameContext;
use App\Services\ProductNaming\ProductPropertyExtractor;
use Illuminate\Console\Command;

/**
 * Rebuilds `products.product_name` using the central ProductNameBuilder rules.
 *
 * Use cases:
 * - After changing naming rules
 * - After improving property extraction
 * - After importing products when you want to regenerate names
 *
 * The command is idempotent: running it multiple times produces stable results.
 */
final class RebuildProductNamesCommand extends Command
{
    /**
     * Example:
     * php artisan products:rebuild-names --manufacturer=1
     * php artisan products:rebuild-names --dry-run
     */
    protected $signature = 'products:rebuild-names
        {--manufacturer= : Limit to a manufacturer_id}
        {--only-empty : Only rebuild if product_name is NULL/empty}
        {--overwrite-original : Overwrite original_product_name (normally we keep it)}
        {--dry-run : Do not write to DB, only show counters}
        {--chunk=500 : Chunk size for processing}';

    protected $description = 'Rebuild products.product_name via ProductNameBuilder (and properties via ProductPropertyExtractor).';

    public function handle(
        DefaultProductNameBuilder $builder,
        ProductPropertyExtractor $extractor,
    ): int {
        $manufacturerId = $this->option('manufacturer');
        $onlyEmpty = (bool) $this->option('only-empty');
        $overwriteOriginal = (bool) $this->option('overwrite-original');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(50, (int) $this->option('chunk'));

        $query = Product::query();

        if ($manufacturerId !== null && $manufacturerId !== '') {
            $query->where('manufacturer_id', (int) $manufacturerId);
        }

        if ($onlyEmpty) {
            $query->where(function ($q) {
                $q->whereNull('product_name')->orWhere('product_name', '');
            });
        }

        $total = (clone $query)->count();
        $this->info("Rebuilding product names for {$total} products".($dryRun ? ' (dry-run)' : '').'…');

        $changed = 0;
        $skipped = 0;

        // Cache manufacturer names by id to reduce DB hits.
        $manufacturerNameCache = [];

        $query
            ->orderBy('id')
            ->chunkById($chunkSize, function ($products) use (
                $builder,
                $extractor,
                $dryRun,
                $overwriteOriginal,
                &$changed,
                &$skipped,
                &$manufacturerNameCache
            ) {
                foreach ($products as $product) {
                    /** @var Product $product */

                    // Decide kind by product_type OR variations existence
                    $kind = $this->resolveKind($product);

                    // Designation source:
                    // Prefer original_product_name, otherwise fallback to existing product_name or slug.
                    $designation = $this->resolveDesignation($product);

                    if ($designation === null || $designation === '') {
                        $skipped++;

                        continue;
                    }

                    $categoryName = ProductNameContext::resolveCategoryName($designation);

                    $properties = $extractor->extract($product);

                    $manufacturerName = $this->resolveManufacturerName(
                        (int) $product->manufacturer_id,
                        $manufacturerNameCache
                    );

                    $ctx = new ProductNameContext(
                        kind: $kind,
                        manufacturerName: $manufacturerName,
                        categoryName: $categoryName,
                        designation: $designation,
                        properties: $properties,
                        manufacturerId: (int) $product->manufacturer_id,
                    );

                    $newName = $builder->build($ctx)->productName;

                    $shouldUpdate = $product->product_name !== $newName;

                    if (! $shouldUpdate && ! $overwriteOriginal) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        if ($shouldUpdate) {
                            $changed++;
                        } else {
                            $skipped++;
                        }

                        continue;
                    }

                    if ($overwriteOriginal || $product->original_product_name === null || $product->original_product_name === '') {
                        $product->original_product_name = $designation;
                    }

                    if ($shouldUpdate) {
                        $product->product_name = $newName;
                    }

                    if ($product->isDirty()) {
                        $product->save();
                        $changed++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->line('');
        $this->info("Done. changed={$changed}, skipped={$skipped}");

        return self::SUCCESS;
    }

    /**
     * Determines product kind for naming rules.
     */
    private function resolveKind(Product $product): ProductKind
    {
        if ($product->product_type === 'variable') {
            return ProductKind::Variable;
        }

        if ($product->product_type === 'set') {
            return ProductKind::Set;
        }

        // Fallback: if variations exist, treat as variable
        if ($product->relationLoaded('variations')) {
            return $product->variations->isNotEmpty() ? ProductKind::Variable : ProductKind::Simple;
        }

        return $product->variations()->exists() ? ProductKind::Variable : ProductKind::Simple;
    }

    /**
     * Resolves the designation/original product name used for naming.
     *
     * Priority:
     * 1) original_product_name (if already present)
     * 2) existing product_name (as last resort)
     * 3) slug (fallback)
     */
    private function resolveDesignation(Product $product): ?string
    {
        if (is_string($product->original_product_name) && trim($product->original_product_name) !== '') {
            return trim($product->original_product_name);
        }

        if (is_string($product->product_name) && trim($product->product_name) !== '') {
            return trim($product->product_name);
        }

        return is_string($product->slug) && trim($product->slug) !== '' ? trim($product->slug) : null;
    }

    /**
     * Resolves manufacturer name for naming with in-memory cache.
     *
     * @param  array<int, string>  $cache
     */
    private function resolveManufacturerName(int $manufacturerId, array &$cache): string
    {
        if ($manufacturerId <= 0) {
            return '';
        }

        if (isset($cache[$manufacturerId])) {
            return $cache[$manufacturerId];
        }

        $m = Manufacturer::query()->find($manufacturerId);

        $name = '';
        if ($m) {
            // Your column is "manufacturer" (not "name")
            $name = (string) ($m->manufacturer ?? '');
        }

        return $cache[$manufacturerId] = $name;
    }
}
