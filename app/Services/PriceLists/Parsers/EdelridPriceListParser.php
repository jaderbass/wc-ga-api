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
            );
        }

        return $result;
    }
}
