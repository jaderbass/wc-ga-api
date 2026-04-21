<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class ResetProductsCommand extends Command
{
    protected $signature = 'products:reset
        {--manufacturer= : Hersteller-ID (optional)}
        {--force : Ohne Rückfrage ausführen}
        {--hours=24 : Wie viele Stunden alte Batches/Failed Jobs entfernt werden sollen}';

    protected $description = 'Löscht Produkte und räumt Queue, Batches und Failed Jobs auf';

    public function handle(): int
    {
        $manufacturerId = $this->option('manufacturer');
        $force = (bool) $this->option('force');
        $hours = (int) $this->option('hours');

        if (! $force) {
            $text = $manufacturerId
                ? "Wirklich Produkte von manufacturer_id={$manufacturerId} löschen und Queue bereinigen?"
                : 'Wirklich ALLE Produkte löschen und Queue bereinigen?';

            if (! $this->confirm($text)) {
                $this->info('Abgebrochen.');

                return self::SUCCESS;
            }
        }

        try {
            $this->newLine();
            $this->info('1/4 Produkte werden gelöscht ...');

            $this->call('products:purge', array_filter([
                '--manufacturer' => $manufacturerId,
                '--force' => true,
            ], fn($value) => $value !== null));

            $this->newLine();
            $this->info('2/4 Queue wird geleert ...');
            $this->call('queue:flush');

            $this->newLine();
            $this->info("3/4 Batches älter als {$hours}h werden entfernt ...");
            $this->call('queue:prune-batches', [
                '--hours' => $hours,
            ]);

            $this->newLine();
            $this->info("4/4 Failed Jobs älter als {$hours}h werden entfernt ...");
            $this->call('queue:prune-failed', [
                '--hours' => $hours,
            ]);

            $this->newLine();
            $this->info('Fertig: Produkte, Queue, Batches und Failed Jobs wurden bereinigt.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Fehler beim Zurücksetzen: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
