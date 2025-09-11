<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductVariation; // <— ggf. Namespace anpassen
use App\Support\Woo\Transformers;

class BackfillVariationFields extends Command
{
  protected $signature = 'variations:backfill
        {--chunk=1000 : Anzahl der Datensätze pro Durchgang}
        {--dry-run : Nur anzeigen, keine Änderungen speichern}';

  protected $description = 'Füllt neue WooCommerce-relevante Spalten in product_variations (Maße/Gewicht/Stock/Attributes) idempotent auf.';

  public function handle(): int
  {
    $chunkSize = (int) $this->option('chunk');
    $dryRun    = (bool) $this->option('dry-run');

    $this->info("Starte Backfill für product_variations (Chunk: {$chunkSize}, DryRun: " . ($dryRun ? 'JA' : 'NEIN') . ")");

    $updated = 0;

    ProductVariation::query()
      ->orderBy('id')
      ->chunkById($chunkSize, function ($vars) use (&$updated, $dryRun) {
        foreach ($vars as $v) {
          $dirty = false;

          // --- Gewicht aus Rohfeld nach grams ---
          if ((int)$v->weight_g === 0 && !empty($v->weight_raw)) {
            $v->weight_g = Transformers::weightToGrams($v->weight_raw);
            $dirty = true;
          }

          // --- Maße aus Rohfeld nach mm ---
          $needDims = ($v->length_mm ?? 0) === 0 || ($v->width_mm ?? 0) === 0 || ($v->height_mm ?? 0) === 0;
          if ($needDims && !empty($v->dimensions_raw)) {
            [$L, $W, $H] = Transformers::dimsToMm($v->dimensions_raw);
            $v->length_mm = $L;
            $v->width_mm  = $W;
            $v->height_mm = $H;
            $dirty = true;
          }

          // --- Stock-Defaults, falls leer ---
          if ($v->stock_status === null || $v->stock_status === '') {
            $v->stock_status = 'instock';
            $dirty = true;
          }
          if ($v->manage_stock === null) {
            $v->manage_stock = false;
            $dirty = true;
          }
          if ($v->stock_quantity === null) {
            $v->stock_quantity = 0;
            $dirty = true;
          }

          // --- attributes_json initialisieren, falls leer & Legacy-Felder vorhanden ---
          // Falls ihr z. B. noch alte Spalten `color`, `size` habt, könnt ihr sie einmalig migrieren:
          $hasAttributesJson = !empty($v->attributes_json);
          $legacyAttrs = [];
          if (!$hasAttributesJson) {
            // Beispiel: nur übernehmen, wenn Spalten existieren (optional)
            if (isset($v->color) && $v->color !== null && $v->color !== '') {
              $legacyAttrs['color'] = (string) $v->color;
            }
            if (isset($v->size) && $v->size !== null && $v->size !== '') {
              $legacyAttrs['size'] = (string) $v->size;
            }
            if (!empty($legacyAttrs)) {
              $v->attributes_json = $legacyAttrs; // Laravel castet array → json, wenn im Model gecastet
              $dirty = true;
            }
          }

          if ($dirty) {
            $updated++;
            if (!$dryRun) {
              $v->save();
            }
          }
        }
        $this->info("Chunk verarbeitet …");
      });

    $this->info("Backfill abgeschlossen. Geänderte Varianten: {$updated}");
    return Command::SUCCESS;
  }
}
