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
}
