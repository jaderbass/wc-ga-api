<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\BaugruppeResolver;
use Illuminate\Console\Command;

class BackfillProductBaugruppeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:backfill-baugruppe {--dry-run : Nur prüfen, nichts speichern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Berechnet die Baugruppe für bestehende Produkte anhand des Produktnamens neu.';

    /**
     * Execute the console command.
     *
     * @param \App\Services\Product\BaugruppeResolver $resolver
     * @return int
     */
    public function handle(BaugruppeResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $unchanged = 0;

        Product::query()
            ->select(['id', 'product_name', 'baugruppe'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($resolver, $dryRun, &$updated, &$unchanged) {
                foreach ($products as $product) {
                    $newValue = $resolver->resolve($product->product_name);

                    if ($product->baugruppe === $newValue) {
                        $unchanged++;
                        continue;
                    }

                    $this->line(sprintf(
                        '#%d | %s | %s -> %s',
                        $product->id,
                        (string) $product->product_name,
                        var_export($product->baugruppe, true),
                        var_export($newValue, true)
                    ));

                    if (! $dryRun) {
                        $product->baugruppe = $newValue;
                        $product->save();
                    }

                    $updated++;
                }
            });

        $this->newLine();
        $this->info('Fertig.');
        $this->line("Geändert: {$updated}");
        $this->line("Unverändert: {$unchanged}");
        $this->line('Modus: ' . ($dryRun ? 'Dry-Run' : 'Speichern'));

        return self::SUCCESS;
    }
}
