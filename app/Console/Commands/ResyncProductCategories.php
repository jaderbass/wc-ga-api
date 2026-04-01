<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Categories\ProductCategorySyncService;
use Illuminate\Console\Command;

/**
 * Synchronisiert die Kategorien aller Produkte anhand der aktuellen Regeln neu.
 */
class ResyncProductCategories extends Command
{
    /**
     * Der Name und die Signatur des Console-Commands.
     *
     * @var string
     */
    protected $signature = 'categories:resync
                            {--product-id= : Nur ein bestimmtes Produkt synchronisieren}
                            {--manufacturer-id= : Nur Produkte eines Herstellers synchronisieren}
                            {--chunk=200 : Chunk-Größe für die Verarbeitung}
                            {--dry-run : Nur anzeigen, welche Produkte verarbeitet würden}';

    /**
     * Die Beschreibung des Console-Commands.
     *
     * @var string
     */
    protected $description = 'Synchronisiert Produktkategorien anhand der aktuellen Kategorie-Regeln neu';

    /**
     * Führt den Command aus.
     */
    public function handle(ProductCategorySyncService $syncService): int
    {
        $productId = $this->option('product-id');
        $manufacturerId = $this->option('manufacturer-id');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()->select(['id', 'manufacturer_id', 'product_name']);

        if ($productId !== null && $productId !== '') {
            $query->whereKey((int) $productId);
        }

        if ($manufacturerId !== null && $manufacturerId !== '') {
            $query->where('manufacturer_id', (int) $manufacturerId);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('Keine passenden Produkte gefunden.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d Produkt(e) gefunden%s.',
            $total,
            $dryRun ? ' (Dry-Run)' : ''
        ));

        $processed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query
            ->orderBy('id')
            ->chunkById($chunkSize, function ($products) use ($syncService, $dryRun, &$processed, $bar): void {
                foreach ($products as $product) {
                    if (! $dryRun) {
                        $syncService->sync($product);
                    }

                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%d Produkt(e) %s.',
            $processed,
            $dryRun ? 'geprüft' : 'neu synchronisiert'
        ));

        return self::SUCCESS;
    }
}
