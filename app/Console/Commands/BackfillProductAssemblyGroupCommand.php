<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\AssemblyGroupResolver;
use Illuminate\Console\Command;

class BackfillProductAssemblyGroupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:backfill-assembly-group {--dry-run : Nur prüfen, nichts speichern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Berechnet assembly_group für Produkte neu und respektiert manuelle Werte.';

    /**
     * Execute the console command.
     *
     * @param \App\Services\Product\AssemblyGroupResolver $resolver
     * @return int
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /** @var \App\Services\Product\AssemblyGroupResolver $resolver */
        $resolver = app(AssemblyGroupResolver::class);

        $checked = 0;
        $changed = 0;

        Product::query()
            ->where('assembly_group_source', 'auto')
            ->select(['id', 'product_name', 'assembly_group', 'assembly_group_source'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($resolver, $dryRun, &$checked, &$changed) {
                foreach ($products as $product) {
                    $checked++;

                    $newValue = $resolver->resolve($product->product_name);

                    if ((int) $product->assembly_group === $newValue) {
                        continue;
                    }

                    $changed++;

                    $this->line(sprintf(
                        '#%d | %s | %s -> %s',
                        $product->id,
                        $product->product_name ?? '—',
                        var_export($product->assembly_group, true),
                        var_export($newValue, true),
                    ));

                    if (! $dryRun) {
                        $product->assembly_group = $newValue;
                        $product->assembly_group_source = 'auto';
                        $product->save();
                    }
                }
            });

        $this->newLine();
        $this->info("Geprüft: {$checked}");
        $this->info("Geändert: {$changed}");
        $this->info($dryRun ? 'Dry-Run: keine Daten gespeichert.' : 'Fertig.');

        return self::SUCCESS;
    }
}
