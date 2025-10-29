<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  // ✅ BEGIN NEW CODE
  /**
   * Map von alt -> neu
   */
  private array $slugMap = [
    'pa_color' => 'pa_farbe',
    'pa_size'  => 'pa_groessen',
  ];

  /**
   * Typisch vorkommende Tabellen und Spalten, in denen Slugs stehen könnten.
   * Die Migration prüft zur Laufzeit, was davon existiert.
   */
  private array $candidateCols = [
    //   table              => [columns...]
    'attributes'           => ['slug'],
    'product_attributes'   => ['slug', 'attribute_slug'],
    'attribute_terms'      => ['attribute_slug'],
    'products'             => ['default_attributes', 'attributes_json'],
    'product_variations'   => ['attributes', 'attribute_json'],
    'variation_attributes' => ['slug'],
  ];

  public function up(): void
  {
    foreach ($this->candidateCols as $table => $cols) {
      if (!Schema::hasTable($table)) {
        continue;
      }
      foreach ($cols as $col) {
        if (!Schema::hasColumn($table, $col)) {
          continue;
        }

        // 1) Direkter Spaltenvergleich (Text/Slug)
        $this->replacePlainValues($table, $col);

        // 2) JSON-Felder (falls Spalte JSON enthält) – vorsichtig behandeln
        $this->tryJsonReplace($table, $col);
      }
    }
  }

  public function down(): void
  {
    // Down-Mapping zurück auf Englisch
    $reverse = array_flip($this->slugMap);

    foreach ($this->candidateCols as $table => $cols) {
      if (!Schema::hasTable($table)) {
        continue;
      }
      foreach ($cols as $col) {
        if (!Schema::hasColumn($table, $col)) {
          continue;
        }
        $this->replacePlainValues($table, $col, $reverse);
        $this->tryJsonReplace($table, $col, $reverse);
      }
    }
  }

  /**
   * Ersetzt Plain-Text Werte in non-JSON Spalten (oder JSON als Text, wenn DB es so speichert)
   */
  private function replacePlainValues(string $table, string $col, ?array $map = null): void
  {
    $map = $map ?? $this->slugMap;
    foreach ($map as $old => $new) {
      try {
        $affected = DB::table($table)
          ->where($col, $old)
          ->update([$col => $new]);
        if ($affected) {
          Log::info('slug_migration_plain_replaced', compact('table', 'col', 'old', 'new', 'affected'));
        }
      } catch (\Throwable $e) {
        Log::warning('slug_migration_plain_replace_failed', [
          'table' => $table,
          'col' => $col,
          'error' => $e->getMessage()
        ]);
      }

      // zusätzlich: falls der Slug als Teilstring in Text/JSON gespeichert ist, REPLACE als Fallback
      try {
        $affected = DB::update("UPDATE {$table} SET {$col} = REPLACE({$col}, ?, ?)", [$old, $new]);
        if ($affected) {
          Log::info('slug_migration_plain_replace_substring', compact('table', 'col', 'old', 'new', 'affected'));
        }
      } catch (\Throwable $e) {
        // ignorieren, wenn Spalte kein Texttyp ist
      }
    }
  }

  /**
   * Versucht JSON-Updates (MySQL/ MariaDB kompatibel), wenn Spalte JSON-ähnliche Daten enthält.
   * Greift defensiv: liest Zeilen in PHP, ersetzt Keys/Values, schreibt zurück.
   */
  private function tryJsonReplace(string $table, string $col, ?array $map = null): void
  {
    $map = $map ?? $this->slugMap;

    try {
      // kleine Batches, damit’s nicht eskaliert
      DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $col, $map) {
        foreach ($rows as $row) {
          $val = $row->{$col};
          if (!is_string($val) || $val === '') {
            continue;
          }
          $decoded = json_decode($val, true);
          if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            continue; // kein JSON
          }

          $updated = $this->replaceInArray($decoded, $map);
          if ($updated !== $decoded) {
            DB::table($table)->where('id', $row->id)->update([
              $col => json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            Log::info('slug_migration_json_row_updated', ['table' => $table, 'col' => $col, 'id' => $row->id]);
          }
        }
      });
    } catch (\Throwable $e) {
      Log::warning('slug_migration_json_replace_failed', [
        'table' => $table,
        'col' => $col,
        'error' => $e->getMessage()
      ]);
    }
  }

  /**
   * Ersetzt rekursiv Keys und string Values in einem Array gemäß Map.
   */
  private function replaceInArray(array $data, array $map): array
  {
    $result = [];
    foreach ($data as $k => $v) {
      $newKey = $k;
      if (is_string($k) && isset($map[$k])) {
        $newKey = $map[$k];
      }

      if (is_array($v)) {
        $v = $this->replaceInArray($v, $map);
      } elseif (is_string($v) && isset($map[$v])) {
        $v = $map[$v];
      }

      $result[$newKey] = $v;
    }
    return $result;
  }
  // ✅ END NEW CODE
};
