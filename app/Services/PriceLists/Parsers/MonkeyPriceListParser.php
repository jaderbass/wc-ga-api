<?php

namespace App\Services\PriceLists\Parsers;

use App\Services\PriceLists\PriceListRow;
use RuntimeException;

/**
 * Monkey-Händlerpreisliste (Excel, über Aliens), z. B. "Monkey Händlerpreisliste 2026".
 *
 * Spalten: Artikelnummer, Artikelbezeichnung, HEK netto, eVK netto, EAN
 *
 * Bezeichnung wie bei Aliens: "MONKEY HW - Produktart MODELL - Eigenschaft",
 * teils mit Zusatz "• SL •" am Ende (→ Markierung "SL").
 */
class MonkeyPriceListParser extends AliensPriceListParser
{
    protected const SUFFIX_MARKER = '/\s*•\s*SL\s*•\s*$/u';

    public function key(): string
    {
        return 'monkey';
    }

    public function manufacturerName(): ?string
    {
        return 'Monkeyhardware';
    }

    public function parse(array $sheets): array
    {
        $rows = reset($sheets) ?: [];
        $headerIndex = null;
        $columns = [];

        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($value) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value))), $row);

            if (in_array('artikelnummer', $normalized, true) && in_array('artikelbezeichnung', $normalized, true)) {
                $headerIndex = $index;
                $columns = array_flip($normalized);
                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Monkey-Preisliste: Kopfzeile mit "Artikelnummer" und "Artikelbezeichnung" nicht gefunden.');
        }

        $value = fn (array $row, string $column): ?string => isset($columns[$column]) ? ($row[$columns[$column]] ?? null) : null;

        $result = [];

        foreach (array_slice($rows, $headerIndex + 1, preserve_keys: true) as $index => $row) {
            $article = trim((string) $value($row, 'artikelnummer'));
            $text = trim((string) $value($row, 'artikelbezeichnung'));

            if ($article === '' || $text === '') {
                continue;
            }

            [$marker, $rest] = self::splitMarker($text);

            if (preg_match(self::SUFFIX_MARKER, $rest) === 1) {
                $rest = trim((string) preg_replace(self::SUFFIX_MARKER, '', $rest));
                $marker = $marker === null ? 'SL' : $marker.PriceListRow::MARKER_SEPARATOR.'SL';
            }

            [$brand, $sourceCategory] = self::splitBrandAndType($rest);

            $result[] = new PriceListRow(
                line: $index + 1,
                articleNumber: $article,
                text: $text,
                brand: $brand,
                // Schrauben ("SCHRAUBE M10x40") haben keine kleingeschriebene Produktart
                sourceCategory: $sourceCategory ?? self::upperCaseType($rest),
                marker: $marker,
                ean: self::digits($value($row, 'ean')),
                purchasePriceCents: self::cents($value($row, 'hek netto')),
                retailPriceCents: self::cents($value($row, 'evk netto')),
            );
        }

        return $result;
    }

    /**
     * "MONKEY HW - SCHRAUBE M10x40 - 10.9" → "Schraube"
     */
    protected static function upperCaseType(string $text): ?string
    {
        $parts = preg_split('/\s+-\s*/u', $text, 3) ?: [];
        $first = preg_split('/\s+/u', trim($parts[1] ?? '')) ?: [];
        $word = $first[0] ?? '';

        return preg_match('/^\p{Lu}{3,}$/u', $word) === 1 ? mb_convert_case($word, MB_CASE_TITLE) : null;
    }
}
