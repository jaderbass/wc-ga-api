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

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s*-\s*/u', '-', $name) ?? $name;

        $name = self::normalizeDimensionSeparators($name);

        $name = Str::of($name)
            ->lower()
            ->replaceMatches('/\b([a-zäöü])([a-zäöü0-9]*)/u', fn(array $m): string => mb_strtoupper($m[1]) . $m[2])
            ->replaceMatches('/-([a-zäöü])/u', fn(array $m): string => '-' . mb_strtoupper($m[1]))
            ->toString();

        foreach (self::ABBREVIATIONS as $abbr) {
            $name = preg_replace(
                '/\b' . preg_quote($abbr, '/') . '\b/ui',
                $abbr,
                $name
            ) ?? $name;
        }

        foreach (self::UNITS as $unit) {
            $name = preg_replace(
                '/\b' . preg_quote($unit, '/') . '\b/ui',
                $unit,
                $name
            ) ?? $name;
        }

        // x nur zwischen Zahlen klein halten
        $name = preg_replace('/(\d)\s*[Xx]\s*(\d)/u', '$1 x $2', $name) ?? $name;

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private static function normalizeDimensionSeparators(string $value): string
    {
        // 10x120 -> 10 x 120
        $value = preg_replace('/(\d)\s*[xX]\s*(\d)/u', '$1 x $2', $value) ?? $value;

        // 8,5mm -> 8,5 mm | 22kN -> 22 kN
        $value = preg_replace(
            '/(\d+(?:[.,]\d+)?)\s*(mm|cm|dm|kg|kn|m|g)\b/ui',
            '$1 $2',
            $value
        ) ?? $value;

        // 8,5 mmx20 m / 8,5 mm x20 m / 8,5 mm X 20 m -> 8,5 mm x 20 m
        $value = preg_replace(
            '/(\d+(?:[.,]\d+)?\s*(?:mm|cm|dm|kg|kn|m|g))\s*[xX]\s*(\d+(?:[.,]\d+)?)/ui',
            '$1 x $2',
            $value
        ) ?? $value;

        // 10 x 120cm -> 10 x 120 cm
        $value = preg_replace(
            '/(\bx\b\s*\d+(?:[.,]\d+)?)\s*(mm|cm|dm|kg|kn|m|g)\b/ui',
            '$1 $2',
            $value
        ) ?? $value;

        return $value;
    }
}
