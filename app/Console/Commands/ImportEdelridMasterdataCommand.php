<?php

namespace App\Console\Commands;

use App\Services\Edelrid\EdelridMasterdataImport;
use App\Support\Imports\SpreadsheetRowReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Edelrid-Stammdaten (Excel aus dem Händlerbereich) übernehmen – nur leere Felder.
 *
 * php artisan edelrid:import-masterdata storage/app/imports/2027-Edelrid-WorkSafety-Stammdaten.xlsx --dry-run
 */
class ImportEdelridMasterdataCommand extends Command
{
    protected $signature = 'edelrid:import-masterdata
                        {file : Stammdaten-Datei (.xlsx)}
                        {--dry-run : Nur berichten, nichts speichern}';

    protected $description = 'Edelrid-Stammdaten: EAN, Gewicht, Texte, Zertifizierung, PDFs, Bilder je Farbe (nur leere Felder)';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);
        $dryRun = (bool) $this->option('dry-run');

        ini_set('memory_limit', '1G');

        $rows = EdelridMasterdataImport::rows(SpreadsheetRowReader::read($path));
        $import = new EdelridMasterdataImport;

        DB::transaction(fn () => $import->run($rows, $dryRun));

        $this->line(($dryRun ? '[Probelauf – nichts gespeichert] ' : '').'Ergebnis '.basename($path).':');

        foreach ($import->counts as $label => $count) {
            $value = match (true) {
                $label === 'nicht in der Datenbank' => $count > 0 ? "<fg=yellow>{$count}</>" : "<fg=green>{$count}</>",
                in_array($label, ['Artikel in der Datei', 'zugeordnet', 'Produkte'], true) => (string) $count,
                default => $count > 0 ? "<fg=green>{$count}</>" : (string) $count,
            };

            $this->line(str_pad($label.':', 42).$value);
        }

        return self::SUCCESS;
    }
}
