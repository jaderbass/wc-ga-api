<?php

namespace App\Services\PriceLists\Parsers;

use App\Services\PriceLists\PriceListRow;
use RuntimeException;

/**
 * Skylotec-Datenfeed "items_datafeed … Professional" (.xlsx oder .csv, 366 Spalten).
 *
 * Eine Kategoriespalte gibt es nicht. Die Herstellerkategorie wird abgeleitet
 * (erste passende Regel gewinnt):
 *   1. Seilart                       → "Seil: Statikseil"
 *   2. Karabiner Kategorie/-typ      → "Karabiner: Verschlusskarabiner"
 *   3. eindeutige eCl@ss-Klasse      → "eCl@ss 40-21-04-01"
 *   4. Seilklemmen-/Rollen-/Verbindungsmittel-Typ → "Seilklemme" / "Rolle" / "Verbindungsmittel"
 *   5. Horizontale/Vertikale Systeme → z. B. "Horizontales Seilsystem"
 *   6. Anschlagpunkt Typ             → z. B. "Stationärer Anschlagpunkt"
 *   7. eCl@ss-Sammelklasse           → "eCl@ss 40-02-01-90"
 *   8. sonst                         → "(ohne eCl@ss)"
 *
 * Listenpreis = "Nettopreis EU Aktuell" (0 = kein Preis).
 * Anwendungsgebiete (mit "|" getrennt) → Einsatzgebiete über die Config.
 */
class SkylotecPriceListParser implements PriceListParser
{
    /** eCl@ss-Klassen, die zu grob sind, um allein die Kategorie zu bestimmen */
    protected const COARSE_ECLASS = ['40-21-07-12', '40-21-01-91', '40-02-90-06'];

    public function key(): string
    {
        return 'skylotec';
    }

    public function manufacturerName(): ?string
    {
        return 'Skylotec';
    }

    public function parse(array $sheets): array
    {
        $rows = reset($sheets) ?: [];
        $header = array_map(fn ($value) => trim((string) $value), $rows[0] ?? []);
        $columns = [];

        foreach ($header as $index => $name) {
            // doppelte Spaltennamen ("Nettopreis EU Aktuell" + Einheit): erste gewinnt
            $columns[mb_strtolower($name)] ??= $index;
        }

        if (! isset($columns['original skylotec artikelnummer'], $columns['produktname'])) {
            throw new RuntimeException('Skylotec-Datenfeed: Spalten "Original Skylotec Artikelnummer" und "Produktname" nicht gefunden.');
        }

        $value = function (array $row, string $column) use ($columns): string {
            $index = $columns[mb_strtolower($column)] ?? null;

            return $index === null ? '' : trim((string) ($row[$index] ?? ''));
        };

        $result = [];

        foreach (array_slice($rows, 1, preserve_keys: true) as $index => $row) {
            $article = $value($row, 'Original Skylotec Artikelnummer');

            if ($article === '') {
                continue;
            }

            $areas = array_values(array_filter(array_map('trim', preg_split('/[|,\/]/', $value($row, 'Anwendungsgebiete')) ?: [])));

            $result[] = new PriceListRow(
                line: $index + 1,
                articleNumber: $article,
                text: $value($row, 'Produktname'),
                brand: 'SKYLOTEC',
                sourceCategory: self::sourceCategory(fn (string $column) => $value($row, $column)),
                ean: self::ean($value($row, 'GTIN')),
                countryOfOrigin: $value($row, 'Ursprungsland') ?: null,
                listPriceCents: self::cents($value($row, 'Nettopreis EU Aktuell')),
                applicationAreas: $areas,
            );
        }

        return $result;
    }

    /**
     * @param  callable(string): string  $value  Spaltenname → Wert
     */
    public static function sourceCategory(callable $value): string
    {
        $eclass = trim(explode('+', $value('E-Class-Nummer'))[0]);
        $specificEclass = $eclass !== '' && ! str_ends_with($eclass, '-90') && ! in_array($eclass, self::COARSE_ECLASS, true);

        if ($value('Seilart') !== '') {
            return 'Seil: '.$value('Seilart');
        }

        if ($value('Karabiner Kategorie') !== '' || $value('Karabinertyp') !== '') {
            return 'Karabiner: '.($value('Karabiner Kategorie') ?: 'Karabiner');
        }

        if ($specificEclass) {
            return 'eCl@ss '.$eclass;
        }

        foreach (['Seilklemmentyp' => 'Seilklemme', 'Rollentyp' => 'Rolle', 'Verbindungsmittel Typ' => 'Verbindungsmittel'] as $column => $label) {
            if ($value($column) !== '') {
                return $label;
            }
        }

        foreach (['Horizontale Systeme', 'Vertikale Systeme', 'Anschlagpunkt Typ'] as $column) {
            if ($value($column) !== '') {
                return $value($column);
            }
        }

        return $eclass !== '' ? 'eCl@ss '.$eclass : '(ohne eCl@ss)';
    }

    protected static function cents(string $value): ?int
    {
        $value = str_replace(',', '.', $value);

        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return (int) round((float) $value * 100);
    }

    protected static function ean(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 8 ? $digits : null;
    }
}
