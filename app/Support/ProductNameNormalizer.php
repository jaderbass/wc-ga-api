<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisiert Hersteller-Produktnamen für Shop-Ausgabe.
 *
 * Regeln:
 * - erster Buchstabe jedes Wortes groß
 * - Bindestrich-Wörter korrekt (Y-Flex, 140-Y)
 * - Norm-Abkürzungen bleiben erhalten (UIAA, EN, ISO, ...)
 * - technische Einheiten bleiben korrekt klein/groß (mm, cm, m, kN, g, kg)
 * - doppelte Leerzeichen entfernen
 */
class ProductNameNormalizer
{
    public static function normalize(string $name): string
    {
        $exceptions = ['UIAA', 'CE', 'ANSI', 'EN', 'ISO'];
        $units = ['mm', 'cm', 'dm', 'm', 'g', 'kg', 'kN'];

        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\s*-\s*/', '-', $name);

        $name = Str::of($name)
            ->lower()
            ->replaceMatches('/\b([a-z])/', fn($m) => strtoupper($m[1]))
            ->replaceMatches('/-([a-z])/', fn($m) => '-' . strtoupper($m[1]))
            ->toString();

        foreach ($exceptions as $abbr) {
            $name = preg_replace('/\b' . preg_quote($abbr, '/') . '\b/i', $abbr, $name);
        }

        foreach ($units as $unit) {
            $name = preg_replace('/\b' . preg_quote($unit, '/') . '\b/i', $unit, $name);
        }

        return $name;
    }
}
