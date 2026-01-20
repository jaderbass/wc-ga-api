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

          if (empty($mapped['manufacturer_id']) || empty($mapped['slug'])) {
            Log::warning('❗ manufacturer_id oder slug fehlt – Datensatz wird ignoriert', [
              'row' => $row,
              'mapped' => $mapped,
            ]);
            continue;
          }

          $product = Product::updateOrCreate(
            [
              'manufacturer_id' => $mapped['manufacturer_id'],
              'slug' => $mapped['slug'],
            ],
            Arr::except($mapped, ['variations', 'images', 'product_type'])
          );

          // Variationen
          $variations = $this->parseVariations($row);

          if (!empty($variations)) {
            foreach ($variations as $variation) {
              $sku = trim((string)($variation['sku'] ?? ''));

              if ($sku === '') {
                Log::warning('❗ Variation ohne SKU – wird übersprungen', [
                  'product_id' => $product->id,
                  'slug' => $product->slug ?? null,
                  'rowNumber' => $rowNumber,
                  'variation' => $variation,
                ]);
                continue;
              }

              $existing = ProductVariation::where('sku', $sku)->first();

              if ($existing && (int)$existing->product_id !== (int)$product->id) {
                Log::warning('❗ Duplicate SKU across products – variation skipped', [
                  'sku' => $sku,
                  'current_product_id' => $product->id,
                  'existing_product_id' => $existing->product_id,
                  'rowNumber' => $rowNumber,
                ]);
                continue;
              }

              ProductVariation::updateOrCreate(
                ['sku' => $sku],
                array_merge($variation, [
                  'product_id' => $product->id,
                  'sku' => $sku, // normalize
                ])
              );
            }

            // product_type korrekt setzen (nur wenn Variationen vorhanden)
            if ($product->product_type !== 'variable') {
              $product->update(['product_type' => 'variable']);
            }
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

          if (!empty($variations) && $product->product_type !== 'variable') {
            $product->update(['product_type' => 'variable']);
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
