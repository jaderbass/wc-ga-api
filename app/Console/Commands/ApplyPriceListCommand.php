<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Services\PriceLists\Parsers\AliensPriceListParser;
use App\Services\PriceLists\Parsers\EdelridPriceListParser;
use App\Services\PriceLists\Parsers\PetzlPriceListParser;
use App\Services\PriceLists\Parsers\PriceListParser;
use App\Services\PriceLists\PriceListApplier;
use App\Services\PriceLists\PriceListReport;
use App\Support\Imports\SpreadsheetRowReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Liest eine Preisliste ein und ergänzt die vorhandenen Produkte
 * (Herstellerkategorie, Markierungen, Listendaten). Kein Shop-Sync.
 *
 * Beispiel:
 *   php artisan pricelist:apply aliens storage/app/imports/aliens-2025-hj2.xlsx --dry-run
 */
class ApplyPriceListCommand extends Command
{
    protected $signature = 'pricelist:apply
                        {list : Art der Liste: aliens, edelrid oder petzl}
                        {file : Pfad zur .xlsx- oder .csv-Datei}
                        {--dry-run : Nur prüfen und berichten, nichts speichern}';

    protected $description = 'Preisliste einlesen und vorhandene Produkte ergänzen (Herstellerkategorie, Markierungen) – kein Shop-Sync';

    public function handle(PriceListApplier $applier): int
    {
        $parser = $this->parser((string) $this->argument('list'));

        if ($parser === null) {
            $this->error('Unbekannte Liste. Erlaubt: aliens, edelrid, petzl');

            return self::INVALID;
        }

        $path = (string) $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);

        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[Probelauf – es wird nichts gespeichert] ' : '').'Lese '.basename($path).' …');

        $report = $applier->apply($parser, SpreadsheetRowReader::readSheets($path), $dryRun);

        $this->printReport($report);
        $this->writeUnmatchedCsv($report, $path);

        return self::SUCCESS;
    }

    protected function parser(string $list): ?PriceListParser
    {
        return match (strtolower($list)) {
            'aliens' => new AliensPriceListParser,
            'edelrid' => new EdelridPriceListParser,
            'petzl' => new PetzlPriceListParser,
            default => null,
        };
    }

    protected function printReport(PriceListReport $report): void
    {
        $this->newLine();
        $this->line("Artikelzeilen in der Liste: {$report->rows}");
        $this->line("zugeordnet:                 {$report->matchedRows()} (zu {$report->products} Produkten)");

        foreach ($report->matchedBy as $method => $count) {
            $this->line("  über {$method}: {$count}");
        }

        $this->line('nicht eindeutig:            '.count($report->ambiguous));
        $this->line('nicht gefunden:             '.count($report->unmatched));

        foreach (array_slice($report->unmatchedByBrand(), 0, 15, true) as $brand => $count) {
            $this->line("  {$brand}: {$count}");
        }

        $this->newLine();
        $this->line("Produkte komplett ausverkauft/EOL (→ nicht in den Shop): {$report->soldOutProducts}");
        $this->line("Produkte im Abverkauf (→ Kategorie SALE):                {$report->clearanceProducts}");
        $this->line("Neue Produkte (→ Hinweis \"lieferbar ab …\"):              {$report->newProducts}");

        $this->newLine();
        $statusLabels = ['vorschlag' => 'mit Vorschlag', 'offen' => 'ohne Vorschlag (offen)', 'vorhanden' => 'schon zugeordnet'];
        $this->line('Herstellerkategorien: '.count($report->sources));

        foreach ($report->sourceStatusCounts() as $status => $count) {
            $this->line('  '.($statusLabels[$status] ?? $status).": {$count}");
        }

        $manufacturers = Manufacturer::query()->pluck('manufacturer', 'id');
        $rows = collect($report->sources)
            ->sortByDesc('products')
            ->take(40)
            ->map(fn (array $source): array => [
                $manufacturers[$source['manufacturer_id']] ?? $source['manufacturer_id'],
                $source['source'],
                $source['products'],
                $source['status'],
                $source['result'],
            ])
            ->values()
            ->all();

        $this->table(['Hersteller', 'Herstellerkategorie', 'Produkte', 'Status', '→ Kategorien'], $rows);

        if ($report->keywordRulesCreated > 0) {
            $this->line("Stichwort-Regeln angelegt: {$report->keywordRulesCreated}");
        }

        if ($report->missingCategories !== []) {
            $this->warn('Kategorien fehlen in der Datenbank (ShopCategoryTreeSeeder ausführen?): '.implode(', ', array_keys($report->missingCategories)));
        }
    }

    protected function writeUnmatchedCsv(PriceListReport $report, string $sourcePath): void
    {
        $rows = array_merge(
            array_map(fn ($row) => ['nicht gefunden', $row], $report->unmatched),
            array_map(fn ($row) => ['nicht eindeutig', $row], $report->ambiguous),
        );

        if ($rows === []) {
            return;
        }

        $lines = ['Status;Zeile;Artikelnr.;EAN;Marke;Herstellerkategorie;Markierung;Text'];

        foreach ($rows as [$status, $row]) {
            $lines[] = implode(';', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                [$status, $row->line, $row->articleNumber, $row->ean, $row->brand, $row->sourceCategory, $row->marker, $row->text],
            ));
        }

        $file = 'exports/preisliste-'.$report->list.'-nicht-zugeordnet-'.now()->format('Y-m-d-His').'.csv';
        Storage::disk('local')->put($file, "\xEF\xBB\xBF".implode("\n", $lines)."\n");

        $this->newLine();
        $this->info('Nicht zugeordnete Zeilen: '.Storage::disk('local')->path($file));
    }
}
