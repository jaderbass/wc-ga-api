<?php

namespace App\Services;

/**
 * Übersetzt Farben automatisch ins Deutsche:
 * - Grundfarben (Yellow -> Gelb)
 * - Kombinationen aus Grundfarben mit Bindestrich (White/Red -> Weiß-Rot)
 * - Hell/Dunkel und Leucht… (Dark gray -> Dunkelgrau, Orange Fluo -> Leuchtorange)
 * - Farbe mit Zusatz (black with positioning bar -> Schwarz)
 * Sonderfarben (Royal Blue, oasis) werden nicht übersetzt, nur groß geschrieben (format()).
 */
final class ColorTranslator
{
    /** @var array<string,string> */
    private const BASIC = [
        'black' => 'Schwarz',
        'white' => 'Weiß',
        'yellow' => 'Gelb',
        'blue' => 'Blau',
        'red' => 'Rot',
        'green' => 'Grün',
        'orange' => 'Orange',
        'gray' => 'Grau',
        'grey' => 'Grau',
        'brown' => 'Braun',
        'purple' => 'Violett',
        'violet' => 'Violett',
        'pink' => 'Pink',
        'beige' => 'Beige',
        'gold' => 'Gold',
        'silver' => 'Silber',
        'turquoise' => 'Türkis',
        'schwarz' => 'Schwarz',
        'weiss' => 'Weiß',
        'weiß' => 'Weiß',
        'gelb' => 'Gelb',
        'blau' => 'Blau',
        'rot' => 'Rot',
        'grün' => 'Grün',
        'gruen' => 'Grün',
        'grau' => 'Grau',
        'braun' => 'Braun',
        'violett' => 'Violett',
        'silber' => 'Silber',
        'türkis' => 'Türkis',
        'tuerkis' => 'Türkis',
    ];

    /** Sichere Übersetzung: Grundfarbe, Kombination, Hell/Dunkel, Leucht… oder Farbe mit Zusatz. */
    public static function auto(string $value): ?string
    {
        $value = self::clean($value);

        if ($value === '') {
            return null;
        }

        if (($single = self::single($value)) !== null) {
            return $single;
        }

        if (preg_match('/^(.+?)\s+with\s+.+$/iu', $value, $m)) {
            return self::auto($m[1]);
        }

        $parts = array_values(array_filter(
            array_map('trim', preg_split('/[\/|,\-]/u', $value) ?: []),
            fn (string $p): bool => $p !== ''
        ));

        if (count($parts) < 2) {
            return null;
        }

        $translated = [];
        foreach ($parts as $part) {
            $single = self::single($part);
            if ($single === null) {
                return null;
            }
            $translated[] = $single;
        }

        return implode('-', $translated);
    }

    /** Schreibweise für Sonderfarben: jeder Wortteil beginnt groß (Oasis-Grey, Forest Green). */
    public static function format(string $value): ?string
    {
        $value = self::clean($value);

        if ($value === '' || ! preg_match('/^\p{L}[\p{L}\s\'\-]*$/u', $value)) {
            return null;
        }

        return preg_replace_callback(
            '/(^|[\s\-])(\p{Ll})/u',
            fn (array $m): string => $m[1].mb_strtoupper($m[2]),
            $value
        );
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value), " \t\n\r-–—");
    }

    private static function basic(string $value): ?string
    {
        return self::BASIC[mb_strtolower(trim($value))] ?? null;
    }

    private static function single(string $value): ?string
    {
        if (($basic = self::basic($value)) !== null) {
            return $basic;
        }

        if (preg_match('/^(dark|light)[\s\-]+(.+)$/iu', $value, $m) && ($base = self::basic($m[2])) !== null) {
            return (strtolower($m[1]) === 'dark' ? 'Dunkel' : 'Hell').mb_strtolower($base);
        }

        if (
            (preg_match('/^(?:neon|hi[\s\-]?vis|high[\s\-]?vis)\s+(.+)$/iu', $value, $m)
                || preg_match('/^(.+?)\s+(?:fluo|fluoro|fluorescent|neon)$/iu', $value, $m))
            && ($base = self::basic($m[1])) !== null
        ) {
            return 'Leucht'.mb_strtolower($base);
        }

        return null;
    }
}
