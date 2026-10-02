<?php

namespace App\Services;

/**
 * Übersetzt einfache Grundfarben automatisch ins Deutsche.
 * Zusammengesetzte Farben und Sonderfarben (z. B. "White Red", "Royal Blue")
 * werden bewusst nicht automatisch übersetzt.
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

    public static function auto(string $value): ?string
    {
        return self::BASIC[mb_strtolower(trim($value))] ?? null;
    }
}
