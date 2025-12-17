<?php

namespace App\Support\Import\SingingRock;

final class Map
{
  /**
   * @param  mixed  $value  String oder Array von Strings (je nach Mapper)
   * @return array<int, string>
   */
  public static function imageUrls(mixed $value): array
  {
    return self::normalizeUrlList($value);
  }

  /**
   * @param  mixed  $value
   * @return array<int, string>
   */
  public static function videoUrls(mixed $value): array
  {
    return self::normalizeUrlList($value);
  }

  /**
   * @param  mixed  $value
   * @return array<int, string>
   */
  private static function normalizeUrlList(mixed $value): array
  {
    $values = is_array($value) ? $value : [$value];

    $out = [];

    foreach ($values as $v) {
      if ($v === null) {
        continue;
      }

      $v = trim((string) $v);

      if ($v === '') {
        continue;
      }

      // falls mal "url1, url2" o.ä. kommt
      foreach (preg_split('/\s*[;,|]\s*/', $v) ?: [] as $candidate) {
        $candidate = trim($candidate);

        if ($candidate === '') {
          continue;
        }

        if (!preg_match('#^https?://#i', $candidate)) {
          continue;
        }

        $out[] = $candidate;
      }
    }

    $out = array_values(array_unique($out));

    return $out;
  }

  /**
   * Baut aus HARNESS_SIZE_TABLE_DESCRIPTION + HARNESS_SIZE_* eine HTML-Tabelle.
   *
   * Erwartet entweder:
   * - ein assoziatives Array: ['HARNESS_SIZE_TABLE_DESCRIPTION' => '...', 'HARNESS_SIZE_UNI' => '...', ...]
   * - oder ein numerisches Array: [description, uni, k1, k2, ...] (Fallback)
   */
  public static function harnessSizeTableHtml(mixed $value): string
  {
    if ($value === null) {
      return '';
    }

    // 1) Input normalisieren: assoc bevorzugt, numeric als Fallback
    $data = is_array($value) ? $value : ['HARNESS_SIZE_TABLE_DESCRIPTION' => (string) $value];

    $description = '';
    $rows = [];

    if (self::isAssocArray($data)) {
      $description = self::scalarize($data['HARNESS_SIZE_TABLE_DESCRIPTION'] ?? '');

      foreach ($data as $key => $raw) {
        if (!is_string($key)) {
          continue;
        }

        if (!str_starts_with($key, 'HARNESS_SIZE_')) {
          continue;
        }

        if ($key === 'HARNESS_SIZE_TABLE_DESCRIPTION') {
          continue;
        }

        $raw = self::scalarize($raw);

        if ($raw === '') {
          continue;
        }

        $size = substr($key, strlen('HARNESS_SIZE_'));
        $cells = self::splitPipeRow($raw);

        $rows[] = [
          'size'  => $size,
          'cells' => $cells,
        ];
      }
    } else {
      // Numeric Fallback: [desc, row1, row2, ...]
      $values = array_values($data);
      $description = trim((string) ($values[0] ?? ''));

      foreach (array_slice($values, 1) as $i => $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
          continue;
        }

        $rows[] = [
          'size'  => 'ROW_' . ($i + 1),
          'cells' => self::splitPipeRow($raw),
        ];
      }
    }

    if ($description === '' || count($rows) === 0) {
      return '';
    }

    // 2) Header aus DESCRIPTION: "D (cm)|D (in) |m (g)" → ["D (cm)", "D (in)", "m (g)"]
    $headers = self::splitPipeRow($description);

    // 3) Tabelle bauen: erste Spalte "Size" + Header
    $thead = '<thead><tr><th>Size</th>';
    foreach ($headers as $h) {
      $thead .= '<th>' . e($h) . '</th>';
    }
    $thead .= '</tr></thead>';

    $tbody = '<tbody>';
    foreach ($rows as $row) {
      $tbody .= '<tr><td>' . e($row['size']) . '</td>';

      $cells = $row['cells'];
      // auf Header-Länge auffüllen (oder kürzen)
      $cells = array_slice(array_pad($cells, count($headers), ''), 0, count($headers));

      foreach ($cells as $c) {
        $tbody .= '<td>' . e($c) . '</td>';
      }

      $tbody .= '</tr>';
    }
    $tbody .= '</tbody>';

    return '<table class="sr-size-table">' . $thead . $tbody . '</table>';
  }

  /**
   * Hängt einen HTML-Block ans Ende einer Beschreibung (nur wenn Block nicht leer ist).
   */
  public static function appendHtmlBlock(string $html, string $block, string $title = 'Size table'): string
  {
    $block = trim($block);
    if ($block === '') {
      return $html;
    }

    $title = trim($title) === '' ? 'Size table' : $title;

    return rtrim($html) . "\n\n" . '<h3>' . e($title) . '</h3>' . "\n" . $block;
  }

  /**
   * Splittet eine "Pipe-Row" robust: "a| b |c" → ["a","b","c"]
   *
   * @return array<int,string>
   */
  private static function splitPipeRow(string $raw): array
  {
    $parts = preg_split('/\s*\|\s*/', trim($raw)) ?: [];

    $parts = array_map(
      static fn($v) => trim((string) $v),
      $parts
    );

    return array_values(array_filter($parts, static fn($v) => $v !== ''));
  }

  private static function isAssocArray(array $arr): bool
  {
    $keys = array_keys($arr);
    return array_filter($keys, 'is_string') !== [];
  }

  public static function toBool(mixed $value): bool
  {
    $v = strtolower(trim((string) $value));

    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
  }

  /**
   * Normalisiert eine Kraftangabe zu einem Display-String.
   *
   * Beispiele:
   * - "22kN"      -> "22 kN"
   * - "22,5 kN"   -> "22.5 kN"
   * - "2200 daN"  -> "2200 daN"
   * - ""/null     -> ""
   */
  public static function forceDisplay(mixed $value): string
  {
    $parsed = self::parseForce($value);

    if ($parsed === null) {
      return '';
    }

    $num = self::trimTrailingZeros($parsed['value']);
    $unit = $parsed['unit'];

    return $num . ' ' . $unit;
  }

  /**
   * Parst eine Kraftangabe und gibt die Kraft als Integer in Newton zurück.
   *
   * Beispiele:
   * - "22 kN"     -> 22000
   * - "22,5 kN"   -> 22500
   * - "2200 daN"  -> 22000
   * - "3000 N"    -> 3000
   *
   * @return int|null Newton als Integer oder null, wenn nicht parsebar/leer
   */
  public static function forceNewtons(mixed $value): ?int
  {
    $parsed = self::parseForce($value);

    if ($parsed === null) {
      return null;
    }

    $v = (float) $parsed['value'];

    return match ($parsed['unit']) {
      'kN'  => (int) round($v * 1000),
      'daN' => (int) round($v * 10),
      'N'   => (int) round($v),
      default => null,
    };
  }

  /**
   * Parst eine Kraftangabe in Wert + Einheit.
   *
   * Unterstützt:
   * - kN, daN, N (case-insensitive)
   * - Dezimal-Komma
   * - "22kN" ohne Leerzeichen
   *
   * @return array{value: float, unit: 'kN'|'daN'|'N'}|null
   */
  private static function parseForce(mixed $value): ?array
  {
    if ($value === null) {
      return null;
    }

    $raw = trim((string) $value);

    if ($raw === '') {
      return null;
    }

    // Dezimal-Komma → Punkt
    $raw = str_replace(',', '.', $raw);

    // Einheit erkennen
    $unit = null;

    if (preg_match('/\bdaN\b/i', $raw)) {
      $unit = 'daN';
    } elseif (preg_match('/\bkN\b/i', $raw)) {
      $unit = 'kN';
    } elseif (preg_match('/\bN\b/i', $raw)) {
      $unit = 'N';
    }

    // Zahl extrahieren (erste Zahl in der Zeichenkette)
    if (!preg_match('/(-?\d+(?:\.\d+)?)/', $raw, $m)) {
      return null;
    }

    $num = (float) $m[1];

    // Wenn keine Einheit dabei ist, konservativ: null (damit wir keine falschen Annahmen treffen)
    if ($unit === null) {
      return null;
    }

    return [
      'value' => $num,
      'unit'  => $unit,
    ];
  }

  /**
   * Parst "25 mm" → 25 (Integer), gibt null bei leer/nicht parsebar.
   */
  public static function mmInt(mixed $value): ?int
  {
    if ($value === null) {
      return null;
    }

    $raw = trim((string) $value);

    if ($raw === '') {
      return null;
    }

    if (!preg_match('/(-?\d+)/', $raw, $m)) {
      return null;
    }

    return (int) $m[1];
  }

  /**
   * "22.0" -> "22", "22.50" -> "22.5"
   */
  private static function trimTrailingZeros(float $value): string
  {
    $s = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

    return $s === '' ? '0' : $s;
  }

  /**
   * Extrahiert Kategorien aus einem SingingRock-$row in eine flache Liste.
   *
   * Unterstützt u. a.:
   * - $row['CATEGORIES'] als String
   * - $row['CATEGORIES']['CATEGORY'] als String oder Array
   * - $row['CATEGORY'] direkt (falls Parser flach mapped)
   *
   * @return array<int, string>
   */
  public static function categoriesList(array $row): array
  {
    $raw = $row['CATEGORIES'] ?? null;

    // Variante A: CATEGORIES ist schon der CATEGORY-Wert (String)
    if (is_string($raw)) {
      $v = trim($raw);
      return $v === '' ? [] : [$v];
    }

    // Variante B: CATEGORIES ist ein Array mit 'CATEGORY'
    if (is_array($raw) && array_key_exists('CATEGORY', $raw)) {
      $cat = $raw['CATEGORY'];

      if (is_string($cat)) {
        $v = trim($cat);
        return $v === '' ? [] : [$v];
      }

      if (is_array($cat)) {
        $out = [];
        foreach ($cat as $c) {
          $v = trim((string) $c);
          if ($v !== '') {
            $out[] = $v;
          }
        }
        return array_values(array_unique($out));
      }
    }

    // Variante C: Parser hat CATEGORY flach auf root gezogen
    if (isset($row['CATEGORY'])) {
      $v = trim((string) $row['CATEGORY']);
      return $v === '' ? [] : [$v];
    }

    // Variante D: Unbekannt/leer
    return [];
  }

  /**
   * Leitet einen Produkttyp ab.
   *
   * Priorität:
   * 1) letzte Kategorie (spezifischste)
   * 2) GROUP_CODE_NAME
   */
  public static function productType(array $row): string
  {
    $categories = self::categoriesList($row);

    if ($categories !== []) {
      return end($categories) ?: '';
    }

    return self::scalarize($row['GROUP_CODE_NAME'] ?? '');
  }

  /**
   * Wandelt gemischte XML-Werte (String | Array | null) in einen skalaren String um.
   *
   * Hintergrund:
   * Der Singing-Rock-XML-Parser liefert viele Tags nicht als String, sondern als Arrays
   * (z. B. leere Tags als [], Inhalte als ['0' => 'Wert'] oder verschachtelte Strukturen).
   *
   * Verhalten:
   * - null            → ''
   * - String          → getrimmt
   * - Array           → rekursiv "flatten", trimmen und mit Leerzeichen zusammenführen
   * - leere Werte     → ''
   *
   * Zweck:
   * - Verhindert "Array to string conversion"-Fehler
   * - Zentrale Normalisierung für Mapping/Transform-Logik
   */
  private static function scalarize(mixed $value): string
  {
    if ($value === null) {
      return '';
    }

    if (is_array($value)) {
      $flat = [];

      array_walk_recursive($value, static function ($v) use (&$flat): void {
        if ($v === null) {
          return;
        }

        $v = trim((string) $v);
        if ($v !== '') {
          $flat[] = $v;
        }
      });

      return implode(' ', $flat);
    }

    return trim((string) $value);
  }

  /**
   * Extrahiert und normalisiert Normen aus NORM1..NORM6.
   *
   * Viele NORM-Felder kommen als Array-Strukturen; außerdem können URLs/IDs oder Text drinstehen.
   * Diese Methode:
   * - scalarize()t jedes Feld
   * - splittet ggf. auf ; , | und Zeilenumbrüche
   * - trimmt, dedupliziert
   *
   * @param array $row Vollständiger XML-Datensatz eines Artikels
   * @return array<int, string> Liste eindeutiger Norm-Strings
   */
  public static function normsList(array $row): array
  {
    $keys = ['NORM1', 'NORM2', 'NORM3', 'NORM4', 'NORM5', 'NORM6'];

    $out = [];

    foreach ($keys as $key) {
      if (!array_key_exists($key, $row)) {
        continue;
      }

      $v = self::scalarize($row[$key]);

      if ($v === '') {
        continue;
      }

      // falls mehrere Werte in einem Feld stehen
      $parts = preg_split('/\s*(?:[;,\|\n\r]+)\s*/', $v) ?: [];

      foreach ($parts as $p) {
        $p = trim($p);

        if ($p === '') {
          continue;
        }

        $out[] = $p;
      }
    }

    $out = array_values(array_unique($out));

    return $out;
  }

  /**
   * Gibt Normen als kompakten Display-String zurück (z. B. "EN 12275 | EN 362").
   */
  public static function normsDisplay(array $row, string $separator = ' | '): string
  {
    $list = self::normsList($row);

    return $list === [] ? '' : implode($separator, $list);
  }

  /**
   * Normalisiert Materialangaben aus MATERIAL_COMPOSITION.
   *
   * Singing Rock liefert teils Arrays/verschachtelte Strukturen. Diese Methode:
   * - scalarize()t den Rohwert
   * - splittet auf ; , | und Zeilenumbrüche
   * - trimmt, dedupliziert
   *
   * @param mixed $value MATERIAL_COMPOSITION (String/Array/null)
   * @return array<int, string> eindeutige Material-Bestandteile
   */
  public static function materialsList(mixed $value): array
  {
    $raw = self::scalarize($value);

    if ($raw === '') {
      return [];
    }

    $parts = preg_split('/\s*(?:[;,\|\n\r]+)\s*/', $raw) ?: [];

    $out = [];
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p !== '') {
        $out[] = $p;
      }
    }

    return array_values(array_unique($out));
  }

  /**
   * Gibt Materialien als Display-String zurück (z. B. "PA | PES").
   */
  public static function materialsDisplay(mixed $value, string $separator = ' | '): string
  {
    $list = self::materialsList($value);

    return $list === [] ? '' : implode($separator, $list);
  }

  /**
   * Normalisiert eine Farbangabe (String/Array/null) zu einem Display-String.
   *
   * Priorität:
   * - VARIETY_COLOUR (bei Varianten)
   * - COLOUR (Fallback)
   */
  public static function colorDisplay(mixed $varietyColor, mixed $color): string
  {
    $v1 = self::scalarize($varietyColor);
    if ($v1 !== '') {
      return self::normalizeColorString($v1);
    }

    $v2 = self::scalarize($color);
    if ($v2 !== '') {
      return self::normalizeColorString($v2);
    }

    return '';
  }

  /**
   * Vereinheitlicht typische Trennzeichen und Whitespace bei Farben.
   *
   * Beispiele:
   * - "black/yellow" -> "black / yellow"
   * - "black - yellow" -> "black / yellow"
   */
  private static function normalizeColorString(string $value): string
  {
    $v = trim($value);

    // vereinheitliche Trennzeichen
    $v = preg_replace('/\s*\/\s*/', ' / ', $v) ?? $v;
    $v = preg_replace('/\s*-\s*/', ' / ', $v) ?? $v;
    $v = preg_replace('/\s*\|\s*/', ' / ', $v) ?? $v;

    // Mehrfachspaces reduzieren
    $v = preg_replace('/\s{2,}/', ' ', $v) ?? $v;

    return trim($v);
  }
}
