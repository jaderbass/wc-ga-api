<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisiert Hersteller-Produktnamen für die Shop-Ausgabe.
 *
 * Regeln:
 * - erster Buchstabe jedes Wortes groß
 * - Bindestrich-Wörter korrekt (Y-Flex, 140-Y)
 * - Norm-Abkürzungen bleiben erhalten (UIAA, EN, ISO, ...)
 * - technische Einheiten bleiben korrekt klein/groß (mm, cm, m, g, kg, kN)
 * - Maßangaben mit "x" bleiben klein (z. B. 6 x 19 mm, 10x120 cm)
 * - doppelte Leerzeichen entfernen
 */
final class ProductNameNormalizer
{
    /**
     * @var array<int, string>
     */
    private const ABBREVIATIONS = [
        'UIAA',
        'CE',
        'ANSI',
        'EN',
        'ISO',
    ];

    /**
     * Schreibweise, wie sie final ausgegeben werden soll.
     *
     * @var array<int, string>
     */
    private const UNITS = [
        'mm',
        'cm',
        'dm',
        'm',
        'g',
        'kg',
        'kN',
    ];

    public static function normalize(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        // Grundbereinigung
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s*-\s*/u', '-', $name) ?? $name;

        // Maßangaben mit "x" vereinheitlichen:
        // 10x120cm   -> 10 x 120 cm
        // 6 X 19 mm  -> 6 x 19 mm
        $name = self::normalizeDimensionSeparators($name);

        // Grundformatierung
        $name = Str::of($name)
            ->lower()
            ->replaceMatches('/\b([a-zäöü])([a-zäöü0-9]*)/u', function (array $matches): string {
                return mb_strtoupper($matches[1]) . $matches[2];
            })
            ->replaceMatches('/-([a-zäöü])/u', function (array $matches): string {
                return '-' . mb_strtoupper($matches[1]);
            })
            ->toString();

        // Abkürzungen wiederherstellen
        foreach (self::ABBREVIATIONS as $abbr) {
            $name = preg_replace(
                '/\b' . preg_quote($abbr, '/') . '\b/u',
                $abbr,
                $name
            ) ?? $name;
        }

        // Einheiten wiederherstellen
        foreach (self::UNITS as $unit) {
            $name = preg_replace(
                '/\b' . preg_quote(mb_strtolower($unit), '/') . '\b/ui',
                $unit,
                $name
            ) ?? $name;
        }

        // "x" nur in Maßangaben klein halten
        $name = preg_replace(
            '/(?<=\b\d)\s*[Xx]\s*(?=\d\b)/u',
            ' x ',
            $name
        ) ?? $name;

        // Falls links/rechts zusätzlich Einheiten stehen:
        // 10 x 120 mm, 6 x 19 mm, 8,5 mm x 20 m
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private static function normalizeDimensionSeparators(string $value): string
    {
        // Zahl x Zahl   => 10 x 120
        $value = preg_replace(
            '/(?<=\d)\s*[xX]\s*(?=\d)/u',
            ' x ',
            $value
        ) ?? $value;

        // ZahlEinheit x ZahlEinheit => 8,5 mm x 20 m
        $value = preg_replace(
            '/(?<=\b\d(?:[.,]\d+)?)\s*(mm|cm|dm|m|g|kg|kn)\s*[xX]\s*(?=\d)/ui',
            ' $1 x ',
            $value
        ) ?? $value;

        // Zahl x ZahlEinheit => 10 x 120cm  -> 10 x 120 cm
        $value = preg_replace(
            '/(?<=\bx\s)(\d+(?:[.,]\d+)?)(mm|cm|dm|m|g|kg|kn)\b/ui',
            '$1 $2',
            $value
        ) ?? $value;

        // Allgemein ZahlEinheit zusammenziehen: 120 cm bleibt okay, 120cm -> 120 cm
        $value = preg_replace(
            '/(\d+(?:[.,]\d+)?)(mm|cm|dm|m|g|kg|kn)\b/ui',
            '$1 $2',
            $value
        ) ?? $value;

        return $value;
    }
}
