<?php

namespace App\Services\PriceLists\Parsers;

use App\Services\PriceLists\PriceListRow;

/**
 * Edelrid-Preisliste (Excel, z. B. "PL 2024_EU").
 *
 * Die Kategorie steht als Zwischenüberschrift in einer eigenen Zeile
 * ("STATIKSEILE | STATIC ROPES"); darunter folgen die Artikel mit
 * Artikelnummer (nur Ziffern) in Spalte 1 und Bezeichnung in Spalte 2.
 * Herstellerkategorie = deutscher Teil der Überschrift ("STATIKSEILE").
 *
 * Ab der Liste 2027 ("PL_2027_WORK SAFETY_EURO", auch als CSV): Spalte 7 =
 * Einheitspreis € ohne MwSt. (= UVP netto → Listenpreis), Spalte 8 = Einheit
 * (PC, M, PA, PU3 …). Der EK wird über die Hersteller-Rabatte berechnet.
 */
class EdelridPriceListParser implements PriceListParser
{
    public function key(): string
    {
        return 'edelrid';
    }

    public function manufacturerName(): ?string
    {
        return 'Edelrid';
    }

    public function parse(array $sheets): array
    {
        $rows = reset($sheets) ?: [];
        $heading = null;
        $result = [];

        foreach ($rows as $index => $row) {
            $filled = array_values(array_filter($row, fn ($value) => $value !== null && trim((string) $value) !== ''));

            if (count($filled) === 1 && str_contains((string) $filled[0], '|') && preg_match('/^\d+$/', trim((string) $filled[0])) !== 1) {
                $heading = trim(explode('|', (string) $filled[0])[0]);

                continue;
            }

            $article = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            if ($heading === null || preg_match('/^\d{6,}$/', $article) !== 1 || $name === '') {
                continue;
            }

            $result[] = new PriceListRow(
                line: $index + 1,
                articleNumber: $article,
                text: trim(implode(' - ', array_filter([$name, $row[2] ?? null, $row[3] ?? null]))),
                brand: 'EDELRID',
                sourceCategory: $heading,
                unit: self::text($row[7] ?? null),
                listPriceCents: self::cents($row[6] ?? null),
            );
        }

        return $result;
    }

    /**
     * "178,57" / "1.125,00" / "425" → Cent; leer oder 0 → null (alter Preis bleibt)
     */
    public static function cents(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) && (float) $value > 0 ? (int) round((float) $value * 100) : null;
    }

    protected static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
