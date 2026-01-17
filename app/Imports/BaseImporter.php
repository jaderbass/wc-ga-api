<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ProductImage;
use App\Support\Concerns\HasImportAuthor;
use Illuminate\Support\Facades\DB;

abstract class BaseImporter
{
  use HasImportAuthor;
  
  protected ?int $authorId = null;

  public function setAuthorId(?int $authorId): static
  {
    $this->authorId = $authorId;
    return $this;
  }

  protected function resolveAuthorId(): int
  {
    return $this->authorId ?? 1;
  }

  /**
   * Muss vom Kind-Importer implementiert werden:
   * - Spaltenzuordnung / Tag-Zuordnung
   */
  abstract protected function mapFields(array $row): array;

  /**
   * Optional: Variationen aus dem Datensatz extrahieren
   */
  protected function parseVariations(array $row): array
  {
    return [];
  }

  /**
   * Optional: Bilder extrahieren
   */
  protected function parseImages(array $row): array
  {
    return [];
  }

  /**
   * Main-Handler: übernimmt eine Collection von Zeilen und importiert sie
   */
  public function handle(array $rows): void
  {
    DB::transaction(function () use ($rows) {
      foreach ($rows as $rowNumber => $row) {
        try {
          $mapped = $this->mapFields($row);

          if (empty($mapped['productnumber'])) {
            Log::warning("❗ Kein productnumber gesetzt – Datensatz wird ignoriert", $mapped);
            continue;
          }

          // Upsert Hauptprodukt
          $product = Product::updateOrCreate(
            ['productnumber' => $mapped['productnumber']],
            Arr::except($mapped, ['variations', 'images'])
          );

          // Variationen
          $variations = $this->parseVariations($row);
          if (!empty($variations)) {
            foreach ($variations as $variation) {
              ProductVariation::updateOrCreate(
                [
                  'product_id' => $product->id,
                  'sku' => $variation['sku'] ?? null,
                ],
                $variation
              );
            }

            // product_type korrekt setzen (einmal pro Produkt)
            if ($product->product_type !== 'variable') {
              $product->update(['product_type' => 'variable']);
            }
          } else {
            // Optional: wenn du "zurück" auf simple willst (meist NICHT nötig)
            // if ($product->product_type !== 'simple') {
            //   $product->update(['product_type' => 'simple']);
            // }
          }


          // Bilder
          $images = $this->parseImages($row);
          if (!empty($images)) {
            foreach ($images as $image) {
              ProductImage::updateOrCreate(
                [
                  'product_id' => $product->id,
                  'url' => $image['url'],
                ],
                $image
              );
            }
          }

          Log::info("✅ Produkt importiert", ['productnumber' => $mapped['productnumber']]);
        } catch (\Throwable $e) {
          Log::error("Fehler beim Import in Zeile {$rowNumber}", [
            'exception' => $e->getMessage(),
            'row' => $row,
          ]);
        }
      }
    });
  }
}
