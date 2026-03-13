<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProductNameNormalizer
{
    public static function normalize(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        // Grundbereinigung
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s*-\s*/u', '-', $name) ?? $name;

        // Maßangaben normalisieren (10x120cm -> 10 x 120 cm)
        $name = self::normalizeDimensionSeparators($name);

        // Grundformatierung
        $name = Str::of($name)
            ->lower()
            ->replaceMatches('/\b([a-zäöü])([a-zäöü0-9]*)/u', function (array $m): string {
                return mb_strtoupper($m[1]) . $m[2];
            })
            ->replaceMatches('/-([a-zäöü])/u', function (array $m): string {
                return '-' . mb_strtoupper($m[1]);
            })
            ->toString();

        // Abkürzungen wiederherstellen
        foreach (self::abbreviations() as $abbr) {
            $name = preg_replace(
                '/\b' . preg_quote($abbr, '/') . '\b/ui',
                $abbr,
                $name
            ) ?? $name;
        }

        // Einheiten wiederherstellen
        foreach (self::units() as $unit) {
            $name = preg_replace(
                '/\b' . preg_quote(mb_strtolower($unit), '/') . '\b/ui',
                $unit,
                $name
            ) ?? $name;
        }

        // x nur zwischen Zahlen klein halten (z. B. 6 x 19 mm)
        $name = preg_replace('/(\d)\s*[Xx]\s*(\d)/u', '$1 x $2', $name) ?? $name;

        // abschließende Whitespace-Bereinigung
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Normalisiert Maßangaben:
     * 10x120cm → 10 x 120 cm
     * 8,5mm → 8,5 mm
     */
    private static function normalizeDimensionSeparators(string $value): string
    {
        // 10x120 → 10 x 120
        $value = preg_replace('/(\d)\s*[xX]\s*(\d)/u', '$1 x $2', $value) ?? $value;

        // 8,5mm → 8,5 mm
        $value = preg_replace(
            '/(\d+(?:[.,]\d+)?)\s*(mm|cm|dm|kg|kn|m|g)\b/ui',
            '$1 $2',
            $value
        ) ?? $value;

        // 8,5 mmx20 m → 8,5 mm x 20 m
        $value = preg_replace(
            '/(\d+(?:[.,]\d+)?\s*(?:mm|cm|dm|kg|kn|m|g))\s*[xX]\s*(\d+(?:[.,]\d+)?)/ui',
            '$1 x $2',
            $value
        ) ?? $value;

        // 10 x 120cm → 10 x 120 cm
        $value = preg_replace(
            '/(\bx\b\s*\d+(?:[.,]\d+)?)\s*(mm|cm|dm|kg|kn|m|g)\b/ui',
            '$1 $2',
            $value
        ) ?? $value;

        return $value;
    }

    /**
     * Abkürzungen aus der Konfiguration laden.
     */
    private static function abbreviations(): array
    {
        $values = config('product_name.abbreviations', []);

        return is_array($values) ? $values : [];
    }

    /**
     * Einheiten aus der Konfiguration laden.
     */
    private static function units(): array
    {
        $values = config('product_name.units', []);

        return is_array($values) ? $values : [];
    }
}
