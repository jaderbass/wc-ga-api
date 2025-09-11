<?php

namespace App\Support\Woo;

final class Transformers
{
  // "1,25 kg", "1250 g", "1.25kg" -> Gramm (int)
  public static function weightToGrams(?string $raw): int
  {
    if (!$raw) return 0;
    $s = strtolower(str_replace([' ', ' '], '', trim($raw))); // inkl. non-breaking space
    $s = str_replace(',', '.', $s);
    if (preg_match('/([\d.]+)\s*kg/', $s, $m)) return (int) round(((float)$m[1]) * 1000);
    if (preg_match('/([\d.]+)\s*g/', $s, $m))  return (int) round(((float)$m[1]));
    // fallback: nur Zahl -> Gramm
    if (preg_match('/^\d+(\.\d+)?$/', $s)) return (int) round((float)$s);
    return 0;
  }

  // "100 x 50 x 20 mm", "10x5x2 cm" -> [L,W,H] in mm (int,int,int)
  public static function dimsToMm(?string $raw): array
  {
    if (!$raw) return [0, 0, 0];
    $s = strtolower(trim($raw));
    $s = str_replace(',', '.', $s);
    // Einheiten
    $unit = 'mm';
    if (str_contains($s, 'cm')) $unit = 'cm';
    if (str_contains($s, 'm'))  $unit = 'm';
    // Split anhand x/×/*/-/space
    $parts = preg_split('/[x×\*\-\/\s]+/', preg_replace('/[^0-9\.\sx×\*\-\/]/', ' ', $s)) ?: [];
    $nums = array_values(array_filter(array_map(fn($p) => $p !== '' ? (float)$p : null, $parts), fn($v) => $v !== null));
    $nums = array_slice($nums, 0, 3);
    $nums = array_pad($nums, 3, 0.0);
    [$l, $w, $h] = $nums;

    $factor = match ($unit) {
      'mm' => 1,
      'cm' => 10,
      'm' => 1000,
      default => 1
    };
    return [(int)round($l * $factor), (int)round($w * $factor), (int)round($h * $factor)];
  }

  public static function slug(string $name): string
  {
    $s = strtolower(trim($name));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s ?? '', '-') ?: uniqid('p-');
  }

  // attributes_json -> WC-Export-Attribute-Spalten
  public static function buildAttributeColumns(array $attributes): array
  {
    // Erwartet: ['color'=>'Blue','size'=>'M'] usw.
    $cols = [];
    $i = 1;
    foreach ($attributes as $key => $val) {
      $tax = 'pa_' . strtolower(preg_replace('/\s+/', '-', $key));
      $cols["attribute_{$i}_name"] = $tax;      // Woo import freundliche Form
      $cols["attribute_{$i}_value"] = (string)$val;
      $cols["attribute_{$i}_visible"] = 1;
      $cols["attribute_{$i}_variation"] = 1;
      $i++;
    }
    return $cols;
  }
}
