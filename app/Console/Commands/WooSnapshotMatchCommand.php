<?php

namespace App\Console\Commands;

use App\Models\WooSnapshot;
use App\Services\ShopComparison\WooSnapshotMatcher;
use Illuminate\Console\Command;

class WooSnapshotMatchCommand extends Command
{
    protected $signature = 'woo:snapshot-match {--snapshot= : ID der Momentaufnahme (Standard: die neueste)}';

    protected $description = 'Gleicht eine vorhandene Shop-Momentaufnahme erneut mit der Datenbank ab, ohne den Shop neu zu lesen.';

    public function handle(WooSnapshotMatcher $matcher): int
    {
        $snapshot = $this->option('snapshot')
            ? WooSnapshot::find((int) $this->option('snapshot'))
            : WooSnapshot::latestCompleted();

        if (! $snapshot || $snapshot->status !== WooSnapshot::STATUS_COMPLETED) {
            $this->error('Keine abgeschlossene Momentaufnahme gefunden. Zuerst "php artisan woo:snapshot" ausführen.');

            return self::FAILURE;
        }

        $matcher->match($snapshot);
        WooSnapshotCommand::printStats($this, $snapshot);

        return self::SUCCESS;
    }
}
