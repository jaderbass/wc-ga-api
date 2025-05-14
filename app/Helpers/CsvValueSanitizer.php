<?php

namespace App\Helpers;

class CsvValueSanitizer
{
  public static function nullIfEmpty(mixed $value): mixed
  {
    return $value === '' ? null : $value;
  }

  public static function toNullableInt(mixed $value): ?int
  {
    return is_numeric($value) ? (int) $value : null;
  }

  public static function toNullableFloat(mixed $value): ?float
  {
    return is_numeric($value) ? (float) str_replace(',', '.', $value) : null;
  }

  public static function toNullableString(mixed $value): ?string
  {
    $value = trim((string) $value);
    return $value === '' ? null : $value;
  }

  public static function toNullableBool(mixed $value): ?bool
  {
    if ($value === '' || is_null($value)) return null;

    $value = strtolower(trim((string) $value));

    return in_array($value, ['1', 'true', 'yes', 'ja'], true) ? true : (in_array($value, ['0', 'false', 'no', 'nein'], true) ? false : null);
  }
}
