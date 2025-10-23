<?php

namespace App\Support;

final class ImportValueNormalizer
{
  /**
   * Extrahiert eine Zahl mit optionaler Einheit aus gemischten Strings.
   * Beachtete Schreibweisen: "1.2 kg", "1,2kg", "1200 g", "12 mm", "1,2cm", "Ø 10mm"
   */
  private static function extractNumericAndUnit(mixed $value): array
  {
    if ($value === null) return [null, null];

    $s = trim((string) $value);
    if ($s === '') return [null, null];

    // Dezimaltrennzeichen vereinheitlichen: Komma -> Punkt
    $sNorm = str_replace(',', '.', $s);

    // Zahl + optional Einheit (auch vor-/nachgestellte Sonderzeichen ignorieren)
    if (!preg_match('/(-?\d+(?:\.\d+)?)\s*([a-zA-Zµμ°"“”\'′″%]*)?/u', $sNorm, $m)) {
      return [null, null];
    }

    $num  = isset($m[1]) ? (float) $m[1] : null;
    $unit = isset($m[2]) ? mb_strtolower(trim($m[2])) : '';

    // Einheit bereinigen (Unicode µ/μ vereinheitlichen)
    $unit = str_replace(['μ', '“', '”', '′', '″'], ['u', '"', '"', "'", '"'], $unit);

    return [$num, $unit ?: null];
  }

  /** Gewicht in Gramm normalisieren. Heuristik: nackte kleine Werte < 5 gelten als kg. */
  public static function toGrams(mixed $value): ?int
  {
    [$num, $unit] = self::extractNumericAndUnit($value);
    if ($num === null) return null;

    $unit = $unit ?? '';
    switch ($unit) {
      case 'g':
      case 'gram':
      case 'grams':
        return (int) round($num);
      case 'kg':
      case 'kilogram':
      case 'kilograms':
        return (int) round($num * 1000);
      case 'mg':
      case 'milligram':
      case 'milligrams':
        return (int) round($num / 1000);
      case '':
        // Keine Einheit angegeben → Heuristik: <5 = kg, sonst g
        return (int) round($num < 5 ? $num * 1000 : $num);
      default:
        // unbekannte Einheit → Zahl als g interpretieren (konservativ)
        return (int) round($num);
    }
  }

  /** Länge in Millimetern normalisieren. Unterstützt mm, cm, m, inch/". */
  public static function toMillimeters(mixed $value): ?int
  {
    [$num, $unit] = self::extractNumericAndUnit($value);
    if ($num === null) return null;

    $unit = $unit ?? '';
    switch ($unit) {
      case 'mm':
        return (int) round($num);
      case 'cm':
        return (int) round($num * 10);
      case 'm':
        return (int) round($num * 1000);
      case 'in':
      case '"':
      case 'inch':
      case 'inches':
        return (int) round($num * 25.4);
      case '':
        // keine Einheit → konservativ als mm interpretieren
        return (int) round($num);
      default:
        return (int) round($num); // unbekannt → mm
    }
  }

  /** Durchmesser in mm (Alias auf toMillimeters, separat für Semantik). */
  public static function diameterToMm(mixed $value): ?int
  {
    return self::toMillimeters($value);
  }

  /** Volumen in ml normalisieren (unterstützt ml, l). */
  public static function toMilliliters(mixed $value): ?int
  {
    [$num, $unit] = self::extractNumericAndUnit($value);
    if ($num === null) return null;

    $unit = $unit ?? '';
    switch ($unit) {
      case 'ml':
        return (int) round($num);
      case 'l':
      case 'lt':
      case 'liter':
      case 'litre':
        return (int) round($num * 1000);
      case '':
        return (int) round($num); // als ml interpretieren
      default:
        return (int) round($num);
    }
  }

  /** Prozent (0..100) als float behalten; gibt null bei Nicht-Zahlen. */
  public static function toPercent(mixed $value): ?float
  {
    [$num, $unit] = self::extractNumericAndUnit($value);
    if ($num === null) return null;
    // Einheit % ignorieren, Zahl belassen
    return (float) $num;
  }

  /** Liste von URLs aus einer Zelle oder aus mehreren Spalten normalisieren. */
  public static function normalizeUrlList(mixed $value, array $splitOn = [',', ';', '|']): ?array
  {
    $items = [];

    if (is_array($value)) {
      $items = $value;
    } elseif (is_string($value)) {
      $pattern = '/\s*[' . preg_quote(implode('', $splitOn), '/') . ']\s*/';
      $items = preg_split($pattern, $value);
    } else {
      return null;
    }

    $items = array_values(array_filter(array_map(function ($u) {
      $u = trim((string) $u);
      return filter_var($u, FILTER_VALIDATE_URL) ? $u : null;
    }, $items)));

    return $items ? array_values(array_unique($items)) : null;
  }

  /** Ersten Satz aus einem Text holen (grobe, robuste Variante). */
  public static function firstSentence(?string $text): ?string
  {
    if (!$text) return null;
    $t = trim($text);
    if ($t === '') return null;

    // Split an Satzendzeichen, aber nicht bei Abkürzungen wie "z. B.", "bzw."
    $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÄÖÜ])/u', $t);
    $first = $parts[0] ?? $t;
    return trim($first);
  }
}
