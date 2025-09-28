<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Class GenerateWooSyncReports
 *
 * Reports:
 *  - missing_variant_sku: Variationen ohne SKU (product_variations)
 *  - parent_has_sku: Variable Parents mit gesetzter SKU (products)
 *  - duplicates: doppelte Woo-SKU je Shop (woo_links)
 *  - fallback_matches: Zuordnungen ohne SKU (confidence<100)
 */
class GenerateWooSyncReports extends Command
{
    protected $signature = 'woo:sync-reports {--disk=local}';
    protected $description = 'Generiert WooCommerce Sync-Reports (Schema: products/product_variations/woo_links).';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $date = now()->format('Ymd_His');

        $missingVariantSku = collect(DB::select("
            SELECT pv.id AS variation_id,
                   pv.product_id AS parent_id,
                   p.product_name AS parent_name
            FROM product_variations pv
            INNER JOIN products p ON p.id = pv.product_id
            WHERE (pv.sku IS NULL OR pv.sku = '')
        " ));

        $parentHasSku = collect(DB::select("
            SELECT p.id AS parent_id, p.product_name, p.sku
            FROM products p
            WHERE p.product_type = 'variable'
              AND p.sku IS NOT NULL
              AND p.sku <> ''
        " ));

        $duplicates = collect(DB::select("
            SELECT shop_id, woo_sku, COUNT(*) AS cnt
            FROM woo_links
            WHERE woo_sku IS NOT NULL AND woo_sku <> ''
            GROUP BY shop_id, woo_sku
            HAVING COUNT(*) > 1
        " ));

        $fallbacks = collect(DB::select("
            SELECT shop_id, local_sku, woo_sku, ean, mpn, confidence
            FROM woo_links
            WHERE confidence < 100
        " ));

        $paths = [];
        $paths[] = $this->writeCsv($disk, "missing_variant_sku_{$date}.csv", $missingVariantSku);
        $paths[] = $this->writeCsv($disk, "parent_has_sku_{$date}.csv", $parentHasSku);
        $paths[] = $this->writeCsv($disk, "duplicates_{$date}.csv", $duplicates);
        $paths[] = $this->writeCsv($disk, "fallback_matches_{$date}.csv", $fallbacks);

        foreach ($paths as $p) $this->info("created: {$p}");
        return self::SUCCESS;
    }

    /**
     * @param string $disk
     * @param string $filename
     * @param \Illuminate\Support\Collection $rows
     * @return string
     */
    private function writeCsv(string $disk, string $filename, $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        $rows = $rows->toArray();
        if (!empty($rows)) {
            fputcsv($fh, array_keys((array)$rows[0]));
            foreach ($rows as $row) fputcsv($fh, (array)$row);
        }
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        Storage::disk($disk)->put($filename, $content);
        return Storage::disk($disk)->path($filename);
    }
}
