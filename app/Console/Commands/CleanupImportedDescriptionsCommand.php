<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductDescriptions\ImportedDescriptionSanitizer;
use Illuminate\Console\Command;

class CleanupImportedDescriptionsCommand extends Command
{
    protected $signature = 'products:cleanup-imported-descriptions
                            {--dry-run : Änderungen nur anzeigen, nicht speichern}';

    protected $description = 'Entfernt Links, verlinkte Bilder und unerwünschte CTAs aus importierten Produktbeschreibungen.';

    public function handle(ImportedDescriptionSanitizer $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()
            ->whereNotNull('description')
            ->where('description', 'like', '%<a%');

        $total = (clone $query)->count();

        $this->info(sprintf(
            'Prüfe %d Produktbeschreibungen%s …',
            $total,
            $dryRun ? ' (dry-run)' : ''
        ));

        $changed = 0;
        $unchanged = 0;
        $errors = 0;

        $query
            ->orderBy('id')
            ->chunkById(100, function ($products) use (
                $sanitizer,
                $dryRun,
                &$changed,
                &$unchanged,
                &$errors
            ) {
                foreach ($products as $product) {
                    try {
                        $cleaned = $sanitizer->sanitize($product->description);

                        if ($cleaned === $product->description) {
                            $unchanged++;

                            continue;
                        }

                        $changed++;

                        $this->line(sprintf(
                            '%s #%d %s',
                            $dryRun ? '[DRY]' : '[SAVE]',
                            $product->id,
                            $product->product_number ?? 'ohne Art.-Nr.'
                        ));

                        if (! $dryRun) {
                            $product->description = $cleaned;
                            $product->save();
                        }
                    } catch (\Throwable $e) {
                        $errors++;

                        $this->error(sprintf(
                            'Fehler bei Produkt #%d: %s',
                            $product->id,
                            $e->getMessage()
                        ));
                    }
                }
            });

        $this->newLine();

        $this->table(
            ['Ergebnis', 'Anzahl'],
            [
                ['Geprüft', $total],
                ['Geändert', $changed],
                ['Unverändert', $unchanged],
                ['Fehler', $errors],
            ]
        );

        if ($dryRun) {
            $this->warn('Dry-run: Es wurden keine Änderungen gespeichert.');
        }

        return $errors > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
