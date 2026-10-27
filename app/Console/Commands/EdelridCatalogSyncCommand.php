<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Edelrid\EdelridCatalogSync;
use App\Support\Imports\SpreadsheetRowReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Edelrid-Sortiment mit einer neuen Preisliste abgleichen (siehe EdelridCatalogSync).
 *
 * php artisan edelrid:catalog-sync "storage/app/imports/PL_2027_WORK SAFETY_EURO_18092026.csv" --dry-run
 *
 * Vorher: php artisan pricelist:apply edelrid <datei> (Listenpreise/EK/Kategorien vorhandener Artikel)
 */
class EdelridCatalogSyncCommand extends Command
{
    protected $signature = 'edelrid:catalog-sync
                        {file : Edelrid-Preisliste (.csv oder .xlsx)}
                        {--dry-run : Nur berichten, nichts ändern}
                        {--author= : E-Mail des Autors neuer Produkte (Standard: erster Admin)}
                        {--details : Alle neuen und ausgelaufenen Artikel auflisten}';

    protected $description = 'Edelrid: ausgelaufene Artikel markieren (offline), neue Artikel anlegen, Nummernwechsel melden';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);
        $dryRun = (bool) $this->option('dry-run');

        $email = $this->option('author');
        $authorId = is_string($email) && $email !== ''
            ? User::query()->where('email', $email)->value('id')
            : User::role('Admin')->orderBy('id')->value('id');

        $rows = EdelridCatalogSync::rows(SpreadsheetRowReader::read($path));
        $sync = new EdelridCatalogSync;

        $this->line(($dryRun ? '[Probelauf – es wird nichts gespeichert] ' : '').count($rows).' Artikel aus '.basename($path));
        DB::transaction(fn () => $sync->run($rows, $dryRun, $authorId));

        $this->newLine();

        foreach ($sync->counts as $label => $count) {
            $this->line(str_pad($label.':', 26).$count);
        }

        foreach (['verdacht' => 'Verdacht auf neue Artikelnummer (nichts geändert, bitte entscheiden)', 'neu' => 'Neu', 'ausgelaufen' => 'Ausgelaufen (offline, bleiben in der Datenbank)'] as $key => $title) {
            $lines = $sync->lines[$key];

            if ($lines === []) {
                continue;
            }

            $this->newLine();
            $this->line($title.':');

            foreach ($this->option('details') || $key === 'verdacht' ? $lines : array_slice($lines, 0, 15) as $line) {
                $this->line('  '.$line);
            }

            if (! $this->option('details') && $key !== 'verdacht' && count($lines) > 15) {
                $this->line('  … '.(count($lines) - 15).' weitere (alle mit --details)');
            }
        }

        if (! $dryRun && $sync->counts['neues Produkt'] + $sync->counts['neue Variante'] > 0) {
            $this->newLine();
            $this->line('Danach: php artisan pricelist:apply edelrid <datei> und php artisan products:rebuild-names');
        }

        return self::SUCCESS;
    }
}
