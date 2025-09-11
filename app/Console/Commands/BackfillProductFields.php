<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product; // passe ggf. Namespace/Model an
use App\Support\Woo\Transformers;

class BackfillProductFields extends Command
{
  protected $signature = 'products:backfill 
        {--chunk=1000 : Anzahl der Datensätze pro Durchgang} 
        {--dry-run : Nur anzeigen, keine Änderungen speichern}';

  protected $description = 'Füllt neue WooCommerce-relevante Spalten (z. B. Maße, Gewicht, Defaults) in der products-Tabelle auf.';

  public function handle(): int
  {
    $chunkSize = (int) $this->option('chunk');
    $dryRun    = $this->option('dry-run');

    $this->info("Starte Backfill für products (Chunkgröße: {$chunkSize}, DryRun: " . ($dryRun ? 'JA' : 'NEIN') . ")");

    $count = 0;

    Product::query()
      ->orderBy('id')
      ->chunkById($chunkSize, function ($products) use (&$count, $dryRun) {
        foreach ($products as $p) {
          $dirty = false;

          // Beispiel: Gewicht backfillen aus altem Feld 'weight_raw'
          if ($p->weight_g === 0 && !empty($p->weight_raw)) {
            $p->weight_g = Transformers::weightToGrams($p->weight_raw);
            $dirty = true;
          }

          // Beispiel: Maße backfillen aus 'dimensions_raw'
          if (
            ($p->length_mm === 0 || $p->width_mm === 0 || $p->height_mm === 0)
            && !empty($p->dimensions_raw)
          ) {
            [$L, $W, $H] = Transformers::dimsToMm($p->dimensions_raw);
            $p->length_mm = $L;
            $p->width_mm  = $W;
            $p->height_mm = $H;
            $dirty = true;
          }

          // Beispiel: Defaults setzen, wenn leer
          if ($p->stock_status === null) {
            $p->stock_status = 'instock';
            $dirty = true;
          }

          if ($dirty) {
            $count++;
            if (!$dryRun) {
              $p->save();
            }
          }
        }
        $this->info("Chunk verarbeitet …");
      });

    $this->info("Backfill abgeschlossen. Geänderte Datensätze: {$count}");
    return Command::SUCCESS;
  }
}
