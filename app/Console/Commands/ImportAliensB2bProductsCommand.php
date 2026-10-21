<?php

namespace App\Console\Commands;

use App\Importers\AliensCsvStreamImporter;
use App\Models\ProductMeta;
use App\Models\User;
use App\Services\Aliens\AliensB2bProductData;
use App\Support\Imports\SpreadsheetRowReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Fehlende Produkte aus den Aliens-B2B-Produktdaten anlegen.
 *
 * php artisan aliens:b2b-import storage/app/imports/Aliens-Produkte.xlsx --dry-run
 *
 * Vorhandene Produkte (Aliens-ID, Artikelnummer oder EAN schon bei uns) werden
 * nicht angefasst. Neue Produkte laufen durch den bisherigen Aliens-Import und
 * bekommen die Markierung product_meta "aliens_b2b_created_at".
 * Preise, Herstellerkategorie und SALE kommen danach über pricelist:apply.
 */
class ImportAliensB2bProductsCommand extends Command
{
    protected $signature = 'aliens:b2b-import
                        {file : Aliens-Produkte als .xlsx oder .csv (B2B-Bereich)}
                        {--dry-run : Nur prüfen und berichten, nichts anlegen}
                        {--limit= : Nur die ersten N fehlenden Produkte anlegen (zum Ausprobieren)}
                        {--only=* : Nur diese Aliens-Produkt-IDs anlegen}
                        {--fill-images : Statt anzulegen: fehlende Bild-Links bei vorhandenen Aliens-Produkten nachtragen}
                        {--author= : E-Mail des Benutzers, der als Autor eingetragen wird (Standard: erster Admin)}';

    protected $description = 'Fehlende Produkte aus den Aliens-B2B-Produktdaten anlegen';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);
        $dryRun = (bool) $this->option('dry-run');

        $this->line(($dryRun ? '[Probelauf – es wird nichts gespeichert] ' : '').'Lese '.basename($path).' …');

        $sheets = SpreadsheetRowReader::readSheets($path);
        $groups = AliensB2bProductData::group($sheets[array_key_first($sheets)] ?? []);
        if ($this->option('fill-images')) {
            return $this->fillImages($groups, $dryRun);
        }

        $existing = AliensB2bProductData::existing($groups);
        $missing = array_diff_key($groups, $existing);

        $this->newLine();
        $this->line('Produkte in der Datei (aktiv): '.count($groups).' ('.array_sum(array_map('count', $groups)).' Varianten)');
        $this->line('schon vorhanden:               '.count($existing));

        foreach (array_count_values($existing) as $reason => $count) {
            $this->line("  über {$reason}: {$count}");
        }

        $variants = array_sum(array_map('count', $missing));
        $this->line('fehlen bei uns:                '.count($missing)." ({$variants} Varianten)");

        $byManufacturer = [];

        foreach ($missing as $rows) {
            $name = AliensB2bProductData::manufacturer($rows[0]) ?: '(ohne Hersteller)';
            $byManufacturer[$name] = ($byManufacturer[$name] ?? 0) + 1;
        }

        arsort($byManufacturer);

        foreach ($byManufacturer as $name => $count) {
            $this->line("  {$name}: {$count}");
        }

        $withoutImages = count(array_filter($missing, fn (array $rows): bool => ($rows[0]['Produkt Alle Bild-Links'] ?? '') === ''));
        $this->line("davon ohne Bild-Links:         {$withoutImages}");

        $listPath = $this->writeReviewList($missing);
        $this->newLine();
        $this->line("Liste der fehlenden Produkte: {$listPath}");

        if ($this->option('only') !== []) {
            $missing = array_intersect_key($missing, array_flip(array_map('strval', (array) $this->option('only'))));
        }

        if ($this->option('limit') !== null) {
            $missing = array_slice($missing, 0, max(0, (int) $this->option('limit')), true);
        }

        if ($dryRun || $missing === []) {
            return self::SUCCESS;
        }

        $this->line('Lege '.count($missing).' Produkte an …');

        $csvPath = storage_path('app/imports/aliens-b2b-neu-'.now()->format('Y-m-d-His').'.csv');
        File::ensureDirectoryExists(dirname($csvPath));
        AliensB2bProductData::writeLegacyCsv($missing, $csvPath);

        $author = $this->author();

        if ($author === null) {
            $this->error('Kein Autor gefunden – bitte --author=<E-Mail> angeben.');

            return self::FAILURE;
        }

        (new AliensCsvStreamImporter(mappingFile: 'aliens'))->setAuthorId($author->id)->import($csvPath);

        $created = 0;
        $stamp = now()->toDateTimeString();

        foreach (ProductMeta::query()->where('key', 'aliens_product_id')->whereIn('value', array_map('strval', array_keys($missing)))->get() as $meta) {
            ProductMeta::query()->updateOrCreate(
                ['product_id' => $meta->product_id, 'variation_id' => null, 'scope' => 'product', 'key' => 'aliens_b2b_created_at'],
                ['value' => $stamp],
            );
            $created++;
        }

        $this->info("Angelegt: {$created} Produkte. Danach Preise/Kategorien mit pricelist:apply aliens (und monkey) einlesen.");

        return self::SUCCESS;
    }

    /**
     * Bild-Links (product_meta "aliens_image_urls") bei vorhandenen Aliens-Produkten
     * nachtragen, wo sie fehlen. Vorhandene Einträge bleiben unverändert.
     *
     * @param  array<string, list<array<string, string>>>  $groups
     */
    protected function fillImages(array $groups, bool $dryRun): int
    {
        $productIds = ProductMeta::query()
            ->where('key', 'aliens_product_id')
            ->whereNull('variation_id')
            ->pluck('product_id', 'value');

        $withImages = ProductMeta::query()
            ->where('key', 'aliens_image_urls')
            ->whereNotNull('value')
            ->where('value', '!=', '[]')
            ->pluck('product_id')
            ->flip();

        $filled = 0;
        $alreadySet = 0;
        $noUrls = 0;

        foreach ($groups as $aliensId => $rows) {
            $productId = $productIds[(string) $aliensId] ?? null;

            if ($productId === null) {
                continue;
            }

            if (isset($withImages[$productId])) {
                $alreadySet++;

                continue;
            }

            $urls = AliensB2bProductData::imageUrls($rows);

            if ($urls === []) {
                $noUrls++;

                continue;
            }

            if (! $dryRun) {
                ProductMeta::query()->updateOrCreate(
                    ['product_id' => $productId, 'variation_id' => null, 'scope' => 'product', 'key' => 'aliens_image_urls'],
                    ['value' => json_encode($urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
                );
            }

            $filled++;
        }

        $this->newLine();
        $this->line('Bild-Links schon vorhanden:   '.$alreadySet);
        $this->line(($dryRun ? 'würden nachgetragen:          ' : 'nachgetragen:                 ').$filled);
        $this->line('ohne Bild-Links in der Datei: '.$noUrls);

        return self::SUCCESS;
    }

    /**
     * Autor der neuen Produkte: --author oder der erste Benutzer mit Rolle "Admin".
     * (Der Importer fällt sonst auf Benutzer-ID 1 zurück, die es nicht mehr geben muss.)
     */
    protected function author(): ?User
    {
        $email = $this->option('author');

        if (is_string($email) && $email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return User::role('Admin')->orderBy('id')->first();
    }

    /**
     * @param  array<string, list<array<string, string>>>  $missing
     */
    protected function writeReviewList(array $missing): string
    {
        $path = storage_path('app/exports/aliens-b2b-fehlend-'.now()->format('Y-m-d-His').'.csv');
        File::ensureDirectoryExists(dirname($path));

        $lines = ['Produkt ID;Hersteller;Produktname;Kategorie;Varianten;Artikelnummern;Bilder'];

        foreach ($missing as $id => $rows) {
            $refs = array_filter(array_map(fn (array $row): string => $row['Kombinationsreferenz'] !== '' ? $row['Kombinationsreferenz'] : $row['Produktreferenz'], $rows));

            $lines[] = implode(';', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [
                $id,
                AliensB2bProductData::manufacturer($rows[0]),
                $rows[0]['Produktname'],
                $rows[0]['Name der Standardkategorie'] ?? '',
                count($rows),
                implode(', ', array_slice($refs, 0, 10)).(count($refs) > 10 ? ' …' : ''),
                ($rows[0]['Produkt Alle Bild-Links'] ?? '') !== '' ? 'ja' : 'nein',
            ]));
        }

        File::put($path, "\u{FEFF}".implode("\n", $lines)."\n");

        return $path;
    }
}
