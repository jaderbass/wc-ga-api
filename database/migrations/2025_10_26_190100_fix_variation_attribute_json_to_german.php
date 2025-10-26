<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  // ✅ BEGIN NEW CODE
  private array $slugMap = [
    'pa_color' => 'pa_farbe',
    'pa_size'  => 'pa_groessen',
  ];

  public function up(): void
  {
    $this->updateJsonColumn('product_variations', 'attributes');
    $this->updateJsonColumn('product_variations', 'attribute_json');
    $this->updateJsonColumn('products', 'default_attributes');
    $this->updateJsonColumn('products', 'attributes_json');
  }

  public function down(): void
  {
    $reverse = array_flip($this->slugMap);
    $this->updateJsonColumn('product_variations', 'attributes', $reverse);
    $this->updateJsonColumn('product_variations', 'attribute_json', $reverse);
    $this->updateJsonColumn('products', 'default_attributes', $reverse);
    $this->updateJsonColumn('products', 'attributes_json', $reverse);
  }

  /**
   * Prüft Tabellenspalte und migriert JSON-Inhalte via PHP.
   */
  private function updateJsonColumn(string $table, string $col, ?array $map = null): void
  {
    $map = $map ?? $this->slugMap;

    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $col)) {
      return;
    }

    DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $col, $map) {
      foreach ($rows as $row) {
        $val = $row->{$col};
        if (!is_string($val) || $val === '') {
          continue;
        }
        $decoded = json_decode($val, true);
        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
          continue;
        }

        $updated = $this->replaceInArray($decoded, $map);
        if ($updated !== $decoded) {
          DB::table($table)->where('id', $row->id)->update([
            $col => json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          ]);
          Log::info('variation_json_slug_updated', ['table' => $table, 'col' => $col, 'id' => $row->id]);
        }
      }
    });
  }

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
