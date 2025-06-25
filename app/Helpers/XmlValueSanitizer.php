<?php

namespace App\Helpers;

class XmlValueSanitizer
{
    public static function toNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    public static function toNullableInt(mixed $value): ?int
    {
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        return $value === '' ? null : (int) $value;
    }

    public static function toBool(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'active']);
    }

    public static function toScaledInt(mixed $value, int $scale = 1): ?int
    {
        if ($value === '' || is_null($value)) return null;

        $value = str_replace(',', '.', (string) $value);
        $value = str_replace([' ', 'cm', 'mm', 'kg', 'g'], '', $value);

        return (int) round((float) $value * $scale);
    }

    public static function toIntCents(mixed $value): ?int
    {
        return self::toScaledInt($value, 100);
    }

    public static function toFloat(mixed $value): ?float
    {
        $value = str_replace(',', '.', (string) $value);
        return $value === '' ? null : (float) $value;
    }

    public static function toScaledFloat(mixed $value, int $scale = 1): ?float
    {
        $value = str_replace(',', '.', (string) $value);
        return $value === '' ? null : round((float) $value * $scale, 2);
    }

    public static function cleanHtml(string $html): string
    {
        return strip_tags(trim($html));
    }

    public static function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function cleanAndDecodeHtml(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::cleanHtml(
            self::decodeEntities($value)
        );
    }
}
