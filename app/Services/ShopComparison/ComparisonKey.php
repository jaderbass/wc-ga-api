<?php

namespace App\Services\ShopComparison;

/**
 * Vereinheitlicht SKU und EAN, damit Shop und Datenbank vergleichbar werden.
 */
final class ComparisonKey
{
    /** Groß geschrieben, ohne Leerzeichen; Bindestriche bleiben, sie gehören zur Artikelnummer. */
    public static function sku(?string $value): ?string
    {
        $value = preg_replace('/[\s\x{00A0}]+/u', '', (string) $value);
        $value = mb_strtoupper((string) $value);

        return $value === '' ? null : $value;
    }

    /** Nur Ziffern, führende Nullen entfernt (GTIN-8/12/13/14 vergleichbar). */
    public static function ean(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $length = strlen((string) $digits);

        if ($length < 8 || $length > 14) {
            return null;
        }

        $key = ltrim((string) $digits, '0');

        return $key === '' ? null : $key;
    }
}
