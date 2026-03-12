<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestAliensImportCommand extends Command
{
    protected $signature = 'import:test-aliens {--path=storage/app/testing/aliens-test-import-17-artikel.csv}';

    protected $description = 'Runs a smoke test import for the Aliens CSV sample file.';

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        if (!is_file($path)) {
            $this->error("CSV not found: {$path}");
            return self::FAILURE;
        }

        $manufacturer = Manufacturer::find(1);

        if (!$manufacturer) {
            $this->error('Manufacturer with ID 1 not found.');
            return self::FAILURE;
        }

        $this->info('Starting Aliens smoke import...');
        $this->line("CSV: {$path}");

        try {
            DB::beginTransaction();

            // TODO:
            // Hier Deinen echten Import-Service / Job synchron aufrufen.
            // Beispiel:
            // app(\App\Services\Import\ImportRunner::class)->runCsvImport(
            //     manufacturer: $manufacturer,
            //     sourcePath: $path,
            // );

            $count = DB::table('products')
                ->where('manufacturer_id', $manufacturer->id)
                ->count();

            $emptyNames = DB::table('products')
                ->where('manufacturer_id', $manufacturer->id)
                ->where(function ($q) {
                    $q->whereNull('product_name')
                        ->orWhere('product_name', '');
                })
                ->count();

            $this->info("Imported products found: {$count}");
            $this->info("Products with empty names: {$emptyNames}");

            $samples = DB::table('products')
                ->where('manufacturer_id', $manufacturer->id)
                ->orderBy('id')
                ->limit(10)
                ->pluck('product_name')
                ->all();

            $this->newLine();
            $this->info('Sample names:');

            foreach ($samples as $name) {
                $this->line('- ' . $name);
            }

            DB::rollBack();

            $this->newLine();
            $this->info('Smoke test finished successfully (transaction rolled back).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error('Smoke test failed: ' . $e->getMessage());
            report($e);

            return self::FAILURE;
        }
    }
}
