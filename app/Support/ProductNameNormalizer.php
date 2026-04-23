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
        $name = self::normalizeValueWithUnits($name);

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
     * Normalisiert Werte mit Einheiten und sorgt für konsistente Abstände.
     *
     * Regeln:
     * - Mehrfache Leerzeichen werden zu einem einzelnen Leerzeichen reduziert
     * - Zahlen und Einheiten werden immer durch genau ein Leerzeichen getrennt
     *   (z. B. "10mm" → "10 mm", "8,5kN" → "8,5 kN", "20%" → "20 %")
     * - Dimensionen wie "10x120" werden zu "10 x 120" normalisiert
     *
     * Unterstützt auch zusammengesetzte Einheiten wie:
     * - m/s, kN/m, cm², m³
     *
     * Die Methode ist die zentrale Stelle für die Formatierung von Attributwerten
     * und wird sowohl im Product Naming als auch bei der Attributverarbeitung verwendet.
     *
     * @param string $value
     * @return string
     */
    private static function normalizeValueWithUnits(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        // Mehrfach-Leerzeichen vereinheitlichen
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        // 10x120 -> 10 x 120
        $value = preg_replace('/(\d)\s*[xX]\s*(\d)/u', '$1 x $2', $value) ?? $value;

        // 8,5mm -> 8,5 mm, 22kN -> 22 kN, 20% -> 20 %
        $value = preg_replace(
            '/(\d+(?:[.,]\d+)?)\s*([[:alpha:]°%][[:alpha:]0-9°%\/²³.-]*)/u',
            '$1 $2',
            $value
        ) ?? $value;

        return trim($value);
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

    /**
     * Normalisiert einen Attributwert für die Ausgabe im Produktnamen.
     *
     * Regeln:
     * - trimmt führende und nachfolgende Leerzeichen
     * - reduziert Mehrfach-Leerzeichen auf ein Leerzeichen
     * - normalisiert Maßangaben wie 11mm -> 11 mm
     * - wandelt vollständig großgeschriebene Werte in Title Case um
     * - berücksichtigt Bindestriche und Leerzeichen als Trenner
     *
     * Beispiele:
     * - BALL-LOCK   -> Ball-Lock
     * - SCREW LOCK  -> Screw Lock
     * - GRAY        -> Gray
     * - 11mm        -> 11 mm
     * - 60 m        -> 60 m
     *
     * @param string|null $value
     * @return string
     */
    public static function normalizeAttributeValue(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = self::normalizeValueWithUnits($value);

        // Nur bei komplett großgeschriebenen Werten umformen.
        // Gemischte oder bereits sauber formatierte Werte bleiben unverändert.
        if ($value !== mb_strtoupper($value, 'UTF-8')) {
            return $value;
        }

        $parts = preg_split('/([-\s]+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (! is_array($parts)) {
            return $value;
        }

        $result = '';

        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[-\s]+$/u', $part)) {
                $result .= $part;
                continue;
            }

            $part = mb_strtolower($part, 'UTF-8');
            $result .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($part, 1, null, 'UTF-8');
        }

        return $result;
    }
}
