<?php

namespace App\Importers;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Support\ImportLog;
use App\Support\ImportValueNormalizer;
use App\Support\Concerns\HasImportAuthor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

/**
 * Class AliensCsvStreamImporter
 *
 * Streamt die Aliens-CSV zeilenweise, um RAM-Spikes (OOM/Killed) zu vermeiden.
 *
 * Konzept:
 * - Parent-Produkt wird über "Produkt-ID" identifiziert und upserted.
 * - Varianten werden über "Kombination-ID" identifiziert und upserted.
 * - Optionales Mapping (product / variation_fields) wird aus config/import_mappings/aliens.php geladen.
 *
 * Wichtig:
 * - Keine globale Gruppierung / kein iterator_to_array() / keine Collections.
 * - Duplicate Header werden eindeutig gemacht (Header_2, Header_3, …).
 */
class AliensCsvStreamImporter
{
  use HasImportAuthor;
  
  protected array $mapping;

  public function __construct(
    protected string $mappingFile = 'aliens',
    protected ?int $manufacturerId = null
  ) {
    $this->mapping = $this->loadMapping($this->mappingFile);
  }

  /**
   * Führt den CSV-Import (streamed) aus.
   *
   * Erwartet bei CSV einen absoluten Pfad.
   *
   * @param string $filePath Absoluter Pfad zur CSV-Datei.
   * @return void
   */
  public function import(string $filePath): void
  {
    $csv = Reader::createFromPath($filePath, 'r');
    $csv->setDelimiter(';');
    $csv->skipEmptyRecords();

    // Wir lesen Header manuell (Aliens kann Duplikate haben).
    $rows = $csv->getRecords(); // Iterator (streamed), liefert numerische Arrays

    $headerRow = null;
    foreach ($rows as $row) {
      $headerRow = $row;
      break;
    }

    if ($headerRow === null) {
      throw new \RuntimeException('CSV appears to be empty.');
    }

    $headers = array_map(
      fn($h) => is_string($h) ? trim($h) : (string) $h,
      $headerRow
    );

    $headers = $this->makeUniqueHeaders($headers);

    ImportLog::debug('CSV Header (unique)', [
      'count'  => count($headers),
      'sample' => array_slice($headers, 0, 10),
    ]);

    // Jetzt erneut Records-Iterator holen und ab Zeile 2 streamen:
    $rows = $csv->getRecords();

    $rowIndex = -1;
    $importedProducts = 0;
    $importedVariations = 0;

    // Cache: Produkt-ID -> Product-Model (damit wir nicht pro Zeile neu aus DB lesen)
    // Wichtig: begrenzen, sonst wächst es ins Unendliche. Wir nutzen eine LRU-light Strategie.
    $productCache = [];
    $productCacheOrder = [];
    $productCacheMax = 500;

    foreach ($rows as $row) {
      $rowIndex++;

      // Header-Zeile überspringen
      if ($rowIndex === 0) {
        continue;
      }

      $assoc = $this->combineRow($headers, $row);

      $productId = $this->cell($assoc, ['Produkt-ID']);
      $combinationId = $this->cell($assoc, ['Kombination-ID']);

      // Ohne IDs macht die Zeile keinen Sinn
      if ($productId === null || $productId === '' || $combinationId === null || $combinationId === '') {
        continue;
      }

      // Parent upsert (cached)
      $product = $this->getOrUpsertProduct(
        $assoc,
        (string) $productId,
        $productCache,
        $productCacheOrder,
        $productCacheMax,
        $importedProducts
      );

      // Variation upsert
      $this->upsertVariation($product, $assoc, (string) $combinationId);
      $importedVariations++;
    }

    Log::info('Aliens CSV import finished (streamed)', [
      'products_upserted'   => $importedProducts,
      'variations_upserted' => $importedVariations,
    ]);
  }

  /**
   * Upsertet ein Produkt anhand der "Produkt-ID" (Aliens Parent ID).
   */
  protected function getOrUpsertProduct(
    array $row,
    string $productId,
    array &$cache,
    array &$order,
    int $max,
    int &$counter
  ): Product {
    if (isset($cache[$productId])) {
      // refresh LRU
      $this->touchCacheKey($productId, $order);
      return $cache[$productId];
    }

    $payload = $this->mapProductPayload($row);

    if (array_key_exists('weight_g', $payload)) {
      $payload['weight_g'] = ImportValueNormalizer::toGrams($payload['weight_g']) ?? 0;
    }

    if (array_key_exists('length_mm', $payload)) {
      $payload['length_mm'] = ImportValueNormalizer::toMillimeters($payload['length_mm']) ?? 0;
    }

    if (array_key_exists('width_mm', $payload)) {
      $payload['width_mm'] = ImportValueNormalizer::toMillimeters($payload['width_mm']) ?? 0;
    }

    if (array_key_exists('height_mm', $payload)) {
      $payload['height_mm'] = ImportValueNormalizer::toMillimeters($payload['height_mm']) ?? 0;
    }

    // Optional: falls du diese Felder überhaupt befüllst (die sind nullable)
    if (array_key_exists('dimension_length_mm', $payload)) {
      $payload['dimension_length_mm'] = ImportValueNormalizer::toMillimeters($payload['dimension_length_mm']);
    }
    if (array_key_exists('dimension_width_mm', $payload)) {
      $payload['dimension_width_mm'] = ImportValueNormalizer::toMillimeters($payload['dimension_width_mm']);
    }
    if (array_key_exists('dimension_height_mm', $payload)) {
      $payload['dimension_height_mm'] = ImportValueNormalizer::toMillimeters($payload['dimension_height_mm']);
    }

    // deterministischer slug: Aliens SEO-URL oder fallback productId
    $slug = $payload['slug'] ?? null;
    if (!is_string($slug) || trim($slug) === '') {
      $slug = 'aliens-' . $productId;
    }

    $payload['slug'] = $slug;
    $payload['manufacturer_id'] = $this->manufacturerId;
    $payload['author_id'] = $this->resolveAuthorId();

    // 1) Produkt primär über Meta finden (ohne Transaktion)
    $product = Product::query()
      ->where('manufacturer_id', $payload['manufacturer_id'])
      ->whereHas('meta', function ($q) use ($productId) {
        $q->where('scope', 'product')
          ->where('key', 'aliens_product_id')
          ->whereNull('variation_id')
          ->where('value', $productId);
      })
      ->first();

    if ($product instanceof Product) {
      // UPDATE-Pfad: kein Transaction-Overhead
      $filtered = $this->filterExistingColumns(Product::class, $payload);

      // slug nicht zwangsupdaten (stabil halten)
      unset($filtered['slug']);

      $product->forceFill($filtered)->save();
    } else {
      // CREATE-Pfad: nur hier Transaktion
      $product = DB::transaction(function () use ($payload, $productId): Product {
        $product = Product::query()->firstOrCreate(
          [
            'manufacturer_id' => $payload['manufacturer_id'],
            'slug'            => $payload['slug'],
          ],
          [
            'manufacturer_id' => $payload['manufacturer_id'],
            'slug'            => $payload['slug'],
            'product_name'    => $payload['product_name'] ?? null,
            'author_id'       => $payload['author_id'],
          ]
        );

        $product->forceFill($this->filterExistingColumns(Product::class, $payload))->save();

        // Meta beim Create-Pfad atomar setzen
        $product->meta()->updateOrCreate(
          ['scope' => 'product', 'key' => 'aliens_product_id', 'variation_id' => null],
          ['value' => $productId]
        );

        return $product;
      });
    }

    // 2) Meta sicherstellen (idempotent) – auch für Update-Pfad
    $product->meta()->updateOrCreate(
      ['scope' => 'product', 'key' => 'aliens_product_id', 'variation_id' => null],
      ['value' => $productId]
    );

    $counter++;

    // Cache pflegen
    $cache[$productId] = $product;
    $order[] = $productId;
    $this->evictCacheIfNeeded($cache, $order, $max);

    return $product;
  }

  /**
   * Upsertet eine Variante anhand der "Kombination-ID" (Aliens Variation ID).
   */
  protected function upsertVariation(Product $product, array $row, string $combinationId): void
  {
    $payload = $this->mapVariationPayload($row);

    $sku = $combinationId;

    ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $sku],
      $this->filterExistingColumns(ProductVariation::class, $payload)
    );

    // Variation-ID als Meta (optional)
    $product->meta()->updateOrCreate(
      ['scope' => 'variation', 'key' => 'aliens_combination_id', 'variation_id' => null],
      ['value' => $combinationId]
    );
  }

  protected function mapProductPayload(array $row): array
  {
    $map = $this->mapping['product'] ?? [];

    $out = [];
    foreach ($map as $dbField => $csvSpec) {
      $val = $this->cell($row, is_array($csvSpec) ? $csvSpec : [$csvSpec]);
      if ($val === null || $val === '') {
        continue;
      }
      $out[$dbField] = $val;
    }

    // Fallback short_description
    if (
      (!isset($out['short_description']) || trim((string) $out['short_description']) === '')
      && !empty($out['description'])
    ) {
      $out['short_description'] = \Illuminate\Support\Str::limit(strip_tags((string) $out['description']), 255);
    }

    return $out;
  }

  protected function mapVariationPayload(array $row): array
  {
    $map = $this->mapping['variation_fields'] ?? [];

    $out = [];
    foreach ($map as $dbField => $csvSpec) {
      $val = $this->cell($row, is_array($csvSpec) ? $csvSpec : [$csvSpec]);
      if ($val === null || $val === '') {
        continue;
      }
      $out[$dbField] = $val;
    }

    return $out;
  }

  protected function cell(array $row, array $candidates): ?string
  {
    foreach ($candidates as $key) {
      if (array_key_exists($key, $row)) {
        $val = $row[$key];
        if ($val === null) {
          continue;
        }
        $s = is_string($val) ? trim($val) : (string) $val;
        if ($s !== '') {
          return $s;
        }
      }
    }
    return null;
  }

  protected function makeUniqueHeaders(array $headers): array
  {
    $seen = [];
    $out = [];

    foreach ($headers as $h) {
      $base = $h !== '' ? $h : 'column';

      if (!isset($seen[$base])) {
        $seen[$base] = 1;
        $out[] = $base;
        continue;
      }

      $seen[$base]++;
      $out[] = $base . '_' . $seen[$base];
    }

    return $out;
  }

  protected function combineRow(array $headers, array $row): array
  {
    $assoc = [];
    $max = max(count($headers), count($row));

    for ($i = 0; $i < $max; $i++) {
      $key = $headers[$i] ?? ('column_' . $i);
      $assoc[$key] = $row[$i] ?? null;
    }

    // trim strings
    foreach ($assoc as $k => $v) {
      if (is_string($v)) {
        $assoc[$k] = trim($v);
      }
    }

    return $assoc;
  }

  protected function filterExistingColumns(string $modelClass, array $payload): array
  {
    $model = app($modelClass);
    $table = $model->getTable();
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing($table);
    $set = array_flip($cols);

    return array_intersect_key($payload, $set);
  }

  protected function touchCacheKey(string $key, array &$order): void
  {
    $pos = array_search($key, $order, true);
    if ($pos !== false) {
      unset($order[$pos]);
      $order = array_values($order);
      $order[] = $key;
    }
  }

  protected function evictCacheIfNeeded(array &$cache, array &$order, int $max): void
  {
    while (count($order) > $max) {
      $oldest = array_shift($order);
      if ($oldest !== null) {
        unset($cache[$oldest]);
      }
    }
  }

  protected function loadMapping(string $name): array
  {
    $fromConfig = config("import_mappings.{$name}");
    if (is_array($fromConfig)) {
      return $fromConfig;
    }

    $path = base_path("config/import_mappings/{$name}.php");
    if (is_file($path)) {
      $map = require $path;
      if (!is_array($map)) {
        throw new \RuntimeException("Mapping file {$path} must return an array.");
      }
      return $map;
    }

    throw new \RuntimeException("Mapping '{$name}' not found via config() or file {$path}");
  }
}
