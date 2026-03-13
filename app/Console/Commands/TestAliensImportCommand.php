<?php

namespace App\Console\Commands;

use App\Importers\AliensCsvStreamImporter;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Console\Command;

class TestAliensImportCommand extends Command
{
    protected $signature = 'import:test-aliens
        {--path=tests/fixtures/imports/aliens-test-import.csv : Path to the Aliens sample CSV}
        {--manufacturer=1 : Manufacturer ID for result checks}';

    protected $description = 'Runs a smoke test import for the Aliens sample CSV.';

    public function handle(): int
    {
        $relativePath = (string) $this->option('path');
        $filePath = base_path($relativePath);
        $manufacturerId = (int) $this->option('manufacturer');

        if (!is_file($filePath)) {
            $this->error("CSV not found: {$filePath}");
            return self::FAILURE;
        }

        $manufacturer = Manufacturer::query()->find($manufacturerId);

        if (!$manufacturer) {
            $this->error("Manufacturer not found: {$manufacturerId}");
            return self::FAILURE;
        }

        $this->info('Aliens smoke import started');
        $this->line("CSV: {$filePath}");
        $this->line("Manufacturer check ID: {$manufacturerId}");
        $this->newLine();

        $beforeProducts = Product::query()
            ->where('manufacturer_id', $manufacturerId)
            ->count();

        try {
            /** @var AliensCsvStreamImporter $importer */
            $importer = app(AliensCsvStreamImporter::class);
            $importer->import($filePath);
        } catch (\Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $afterProducts = Product::query()
            ->where('manufacturer_id', $manufacturerId)
            ->count();

        $emptyNames = Product::query()
            ->where('manufacturer_id', $manufacturerId)
            ->where(function ($query) {
                $query->whereNull('product_name')
                    ->orWhere('product_name', '');
            })
            ->count();

        $samples = Product::query()
            ->where('manufacturer_id', $manufacturerId)
            ->latest('id')
            ->limit(10)
            ->get(['id', 'product_name', 'original_product_name'])
            ->reverse()
            ->values();

        $this->info('Smoke test finished.');
        $this->newLine();
        $this->line('Products before import: ' . $beforeProducts);
        $this->line('Products after import:  ' . $afterProducts);
        $this->line('Delta:                  ' . ($afterProducts - $beforeProducts));
        $this->line('Empty product_name:     ' . $emptyNames);

        $this->newLine();
        $this->info('Sample products:');

        foreach ($samples as $product) {
            $this->line(sprintf(
                '- [%d] %s | original: %s',
                $product->id,
                $product->product_name ?? '(leer)',
                $product->original_product_name ?? '(leer)'
            ));
        }

        return self::SUCCESS;
    }
}
