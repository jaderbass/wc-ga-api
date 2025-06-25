<?php

namespace App\Helpers;

class XmlValueSanitizer
{
    public static function toNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    public static function toIntCents(mixed $value): ?int
    {
        if ($value === '' || $value === null) return null;

        $normalized = str_replace(',', '.', (string) $value);
        return (int) round((float) $normalized * 100);
    }

    public static function toScaledInt(mixed $value, int $scale = 1): ?int
    {
        if ($value === '' || $value === null) return null;

        $normalized = str_replace(',', '.', (string) $value);
        $normalized = str_replace([' ', 'cm', 'mm', 'kg', 'g'], '', $normalized);

        return (int) round((float) $normalized * $scale);
    }

    public static function toBool(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'active']);
    }
}
