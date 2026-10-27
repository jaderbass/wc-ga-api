<?php

namespace App\Support;

use Illuminate\Support\Str;

class TextNormalizer
{
    public static function plain(string $text): string
    {
        return (string) Str::of($text)
            ->replaceMatches('/<br\s*\/?>/i', ' ')
            ->stripTags()
            ->squish();
    }

    /**
     * Erster Satz eines (HTML-)Textes – Regel für Kurz- und Warenkorbbeschreibung.
     */
    public static function firstSentence(?string $html): ?string
    {
        $text = html_entity_decode(self::plain((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($text === '') {
            return null;
        }

        return preg_match('/^(.{20,}?[.!?])(\s|$)/u', $text, $m) ? $m[1] : Str::limit($text, 250);
    }
}
