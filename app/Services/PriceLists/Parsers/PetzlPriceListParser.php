<?php

namespace App\Services\PriceLists\Parsers;

use App\Services\PriceLists\PriceListRow;
use RuntimeException;

/**
 * Petzl-Preisliste "PRO BASIC Price List" (Excel).
 *
 * - Blatt "Logistic Info": eine Zeile je Artikel mit Status (NEW/EOL), Datum,
 *   Product name, Reference, Category, Subcategory, EAN Code, Customs, Made in,
 *   Gewicht …  → Herstellerkategorie = "Category > Subcategory"
 * - Blatt "Price List": Listenpreis netto je Reference (Spalte C = Reference,
 *   letzte Zahl der Zeile = Preis)
 *
 * Status: EOL → Markierung "EOL TT/MM/JJJJ" (nicht mehr lieferbar),
 *         NEW → "NEW TT/MM/JJJJ" + available_from (Lieferzeit "ab …").
 */
class PetzlPriceListParser implements PriceListParser
{
    public function key(): string
    {
        return 'petzl';
    }

    public function manufacturerName(): ?string
    {
        return 'Petzl';
    }

    public function parse(array $sheets): array
    {
        $logistic = $this->findSheet($sheets, 'logistic');

        if ($logistic === null) {
            throw new RuntimeException('Petzl-Preisliste: Blatt "Logistic Info" nicht gefunden.');
        }

        $prices = $this->prices($this->findSheet($sheets, 'price list') ?? []);

        $headerIndex = null;
        $columns = [];

        foreach ($logistic as $index => $row) {
            $normalized = array_map(fn ($value) => self::headerKey($value), $row);

            if (in_array('reference', $normalized, true) && in_array('category', $normalized, true)) {
                $headerIndex = $index;

                // erste Spalte je Name ("Unit" kommt mehrfach vor)
                foreach ($normalized as $position => $name) {
                    if ($name !== '' && ! isset($columns[$name])) {
                        $columns[$name] = $position;
                    }
                }

                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Petzl-Preisliste: Kopfzeile mit "Reference" und "Category" nicht gefunden.');
        }

        $value = fn (array $row, string $column): ?string => isset($columns[$column])
            ? (($v = trim((string) ($row[$columns[$column]] ?? ''))) === '' ? null : $v)
            : null;

        $result = [];

        foreach (array_slice($logistic, $headerIndex + 1, preserve_keys: true) as $index => $row) {
            $reference = $value($row, 'reference');

            if ($reference === null) {
                continue;
            }

            $category = $value($row, 'category');
            $subcategory = $value($row, 'subcategory');
            $status = strtoupper((string) $value($row, 'status'));
            $date = $value($row, 'date status');
            $marker = in_array($status, ['NEW', 'EOL'], true) ? trim($status.' '.$date) : null;

            $result[] = new PriceListRow(
                line: $index + 1,
                articleNumber: $reference,
                text: trim(implode(' - ', array_filter([$value($row, 'product name'), $value($row, 'description')]))),
                brand: 'PETZL',
                sourceCategory: $category !== null ? $category.($subcategory !== null ? ' > '.$subcategory : '') : null,
                marker: $marker,
                ean: self::digits($value($row, 'ean code')),
                // Einheit steht in der Spalte direkt nach dem Gewicht
                weightGrams: self::grams(
                    $value($row, 'product packed weight'),
                    isset($columns['product packed weight']) ? ($row[$columns['product packed weight'] + 1] ?? null) : null,
                ),
                customsTariff: $value($row, 'customs'),
                countryOfOrigin: $value($row, 'made in'),
                listPriceCents: $prices[self::referenceKey($reference)] ?? null,
                availableFrom: $status === 'NEW' ? self::isoDate($date) : null,
            );
        }

        return $result;
    }

    /**
     * @param  array<string, list<list<string|null>>>  $sheets
     * @return list<list<string|null>>|null
     */
    protected function findSheet(array $sheets, string $needle): ?array
    {
        foreach ($sheets as $name => $rows) {
            if (str_contains(mb_strtolower($name), $needle)) {
                return $rows;
            }
        }

        return null;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return array<string, int> Reference => Cent
     */
    protected function prices(array $rows): array
    {
        $prices = [];

        foreach ($rows as $row) {
            // Artikelnummern auch kurz oder mit Leerzeichen ("C25", "C07 120")
            $reference = self::referenceKey($row[2] ?? null);

            if (preg_match('/^[A-Z]\d/', $reference) !== 1) {
                continue;
            }

            foreach (array_reverse(array_slice($row, 3)) as $cell) {
                $cell = str_replace([',', '€', ' '], ['.', '', ''], trim((string) $cell));

                if (is_numeric($cell)) {
                    $prices[$reference] = (int) round((float) $cell * 100);
                    break;
                }
            }
        }

        return $prices;
    }

    protected static function referenceKey(?string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/u', '', (string) $value));
    }

    /**
     * "Product  packed\nWeight" → "product packed weight"
     */
    protected static function headerKey(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower((string) $value)));
    }

    protected static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * Gewicht in Gramm; die Liste gibt die Einheit in der Spalte danach an (meist KG).
     */
    protected static function grams(?string $value, ?string $unit): ?int
    {
        $value = str_replace(',', '.', trim((string) $value));

        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        $factor = strtoupper(trim((string) $unit)) === 'G' ? 1 : 1000;

        return (int) round((float) $value * $factor);
    }

    /**
     * "01/03/2027" (TT/MM/JJJJ) oder Excel-Datumszahl → "2027-03-01"
     */
    protected static function isoDate(?string $value): ?string
    {
        // Excel-Datum als Zahl (Tage seit 30.12.1899)
        if (is_numeric($value) && (float) $value > 30000) {
            return date('Y-m-d', (int) round(((float) $value - 25569) * 86400));
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim((string) $value), $m) !== 1) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
}
