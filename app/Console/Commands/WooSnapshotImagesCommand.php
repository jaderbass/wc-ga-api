<?php

namespace App\Console\Commands;

use App\Models\WooSnapshot;
use App\Services\ShopComparison\WooSnapshotImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WooSnapshotImagesCommand extends Command
{
    protected $signature = 'woo:snapshot-images
        {--snapshot= : ID der Momentaufnahme (Standard: die neueste)}
        {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Übernimmt Bild-Links aus einer Shop-Momentaufnahme für Produkte, die in der Datenbank noch kein Bild haben (der Shop wird nicht gelesen oder geändert).';

    public function handle(): int
    {
        $snapshot = $this->option('snapshot')
            ? WooSnapshot::find((int) $this->option('snapshot'))
            : WooSnapshot::latestCompleted();

        if (! $snapshot || $snapshot->status !== WooSnapshot::STATUS_COMPLETED || $snapshot->matched_at === null) {
            $this->error('Keine abgeglichene Momentaufnahme gefunden. Zuerst "php artisan woo:snapshot --shop=<ID>" ausführen.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->line(($dryRun ? '[Probelauf – es wird nichts gespeichert] ' : '')."Momentaufnahme {$snapshot->id} (Shop {$snapshot->shop_id}, {$snapshot->created_at})");

        $images = new WooSnapshotImages;
        DB::transaction(fn () => $images->run($snapshot, $dryRun));

        foreach ($images->lines as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();

        foreach ($images->counts as $label => $count) {
            $this->line(str_pad($label.':', 28).$count);
        }

        return self::SUCCESS;
    }
}
