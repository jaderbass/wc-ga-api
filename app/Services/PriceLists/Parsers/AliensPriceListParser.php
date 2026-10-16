<?php

namespace App\Services\PriceLists\Parsers;

use App\Services\PriceLists\PriceListRow;
use RuntimeException;

/**
 * Aliens-Preisliste "Artikelstammdaten" (Excel).
 *
 * Spalten: Artikelnr., Kurztext, HEK netto, UVP netto, Gewicht (kg), Einheit,
 * Warentarifnr., Ursprungsland, EAN Barcode
 *
 * Kurztext: "[Markierung] MARKE - Produktart MODELL - Eigenschaft"
 * z. B. "-- ABVERKAUF -- KONG - Alukarabiner PADDLE - BENT GATE - schwarz"
 * → Marke KONG, Produktart (= Herstellerkategorie) "Alukarabiner", Markierung ABVERKAUF
 */
class AliensPriceListParser implements PriceListParser
{
    protected const MARKER = '/^\s*(?<m>--\s*ABVERKAUF\s*--|--\s*AUSVERKAUFT\s*--\s*(?<date>\d\d\/\d\d)?|\*\*\s*NEU\s*(?:\d\d\/\d\d)?\s*\*\*|##\s*DNV\s*##|--\s*SONDERANFERTIGUNG\s*--)\s*/iu';

    /** Wörter, die am Ende einer Produktart nichts bedeuten ("Gehörschutz medium für"). */
    protected const TRAILING_STOPWORDS = ['für', 'mit', 'm.', 'inkl.', 'zu', 'und', 'aus', 'ohne'];

    public function key(): string
    {
        return 'aliens';
    }

    public function manufacturerName(): ?string
    {
        return null;
    }

    public function parse(array $sheets): array
    {
        $rows = reset($sheets) ?: [];
        $headerIndex = null;
        $columns = [];

        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($value) => mb_strtolower(trim((string) $value)), $row);

            if (in_array('artikelnr.', $normalized, true) && in_array('kurztext', $normalized, true)) {
                $headerIndex = $index;
                $columns = array_flip($normalized);
                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Aliens-Preisliste: Kopfzeile mit "Artikelnr." und "Kurztext" nicht gefunden.');
        }

        $value = fn (array $row, string $column): ?string => isset($columns[$column]) ? ($row[$columns[$column]] ?? null) : null;

        $result = [];

        foreach (array_slice($rows, $headerIndex + 1, preserve_keys: true) as $index => $row) {
            $article = trim((string) $value($row, 'artikelnr.'));
            $text = trim((string) $value($row, 'kurztext'));

            if ($article === '' || $text === '') {
                continue;
            }

            [$marker, $rest] = self::splitMarker($text);
            [$brand, $sourceCategory] = self::splitBrandAndType($rest);

            $result[] = new PriceListRow(
                line: $index + 1,
                articleNumber: $article,
                text: $text,
                brand: $brand,
                sourceCategory: $sourceCategory,
                marker: $marker,
                ean: self::digits($value($row, 'ean barcode')),
                purchasePriceCents: self::cents($value($row, 'hek netto')),
                retailPriceCents: self::cents($value($row, 'uvp netto')),
                weightGrams: self::grams($value($row, 'gewicht')),
                unit: $value($row, 'einheit'),
                customsTariff: $value($row, 'warentarifnr.'),
                countryOfOrigin: $value($row, 'ursprungsland'),
            );
        }

        return $result;
    }

    /**
     * @return array{0: ?string, 1: string} [Markierung, Rest]
     */
    public static function splitMarker(string $text): array
    {
        if (preg_match(self::MARKER, $text, $match) !== 1) {
            return [null, $text];
        }

        $raw = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $match['m']));
        $marker = $raw === 'AUSVERKAUFT' && ! empty($match['date']) ? 'AUSVERKAUFT '.$match['date'] : $raw;

        return [$marker, trim(substr($text, strlen($match[0])))];
    }

    /**
     * "KONG - Alukarabiner PADDLE - BENT GATE" → ["KONG", "Alukarabiner"]
     *
     * Die Produktart sind die Wörter nach der Marke bis zum ersten Wort in
     * GROSSBUCHSTABEN (Modellname) oder mit Ziffern (Maße).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function splitBrandAndType(string $text): array
    {
        $parts = preg_split('/\s+-\s*/u', $text, 2) ?: [$text];

        if (count($parts) < 2) {
            return [null, null];
        }

        $brand = trim($parts[0]);
        $words = preg_split('/\s+/u', trim($parts[1])) ?: [];
        $type = [];

        foreach ($words as $word) {
            $letters = preg_replace('/[^\p{L}]/u', '', $word) ?? '';

            if (preg_match('/\d/', $word) === 1 || (mb_strlen($letters) > 1 && $letters === mb_strtoupper($letters))) {
                break;
            }

            $type[] = $word;
        }

        while ($type !== [] && in_array(mb_strtolower(end($type)), self::TRAILING_STOPWORDS, true)) {
            array_pop($type);
        }

        $type = trim(implode(' ', $type), ' ,-');

        return [$brand !== '' ? $brand : null, $type !== '' ? $type : null];
    }

    protected static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    protected static function cents(?string $value): ?int
    {
        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) ? (int) round((float) $value * 100) : null;
    }

    protected static function grams(?string $value): ?int
    {
        $value = str_replace(',', '.', trim((string) $value));

        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return (int) round((float) $value * 1000);
    }
}
