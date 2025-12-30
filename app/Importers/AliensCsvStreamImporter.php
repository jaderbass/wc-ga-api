<?php

namespace App\Importers;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ProductMeta;
use App\Models\ImportRun;
use App\Support\ImportLog;
use App\Support\ImportValueNormalizer;
use App\Support\Concerns\HasImportAuthor;
use Illuminate\Support\Str;
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
  protected ?string $runId = null;


  public function __construct(
    protected string $mappingFile = 'aliens',
    protected ?int $manufacturerId = null
  ) {
    $this->mapping = $this->loadMapping($this->mappingFile);
  }

  /**
   * Setzt die Import-Run-ID für Progress-Tracking.
   *
   * Wird vom RunManufacturerImportJob gesetzt, um diesem Importer
   * den zugehörigen ImportRun zuzuordnen. Ermöglicht das laufende
   * Aktualisieren von processed_rows während eines Streaming-Imports
   * sowie das Anzeigen des Fortschritts im UI via SSE.
   *
   * @param  string|null  $runId  UUID des ImportRuns oder null, wenn kein Tracking gewünscht ist.
   * @return static
   */
  public function setRunId(?string $runId): static
  {
    $this->runId = $runId;
    return $this;
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
      $combinationRef = $this->cell($assoc, ['Kombinations-Referenz']);
      $combinationRef = is_string($combinationRef)
        ? trim($combinationRef)
        : (is_numeric($combinationRef) ? (string) $combinationRef : null);

      // Ohne Produkt-ID macht die Zeile keinen Sinn
      if ($productId === null || $productId === '') {
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

      // Features immer übernehmen (auch aus Feature-only Zeilen)
      $this->upsertProductFeaturesMeta($product, $assoc);

      // Nur echte Variantenzeilen upserten (PrestaShop: erkennbar an Kombinations-Referenz)
      $isVariationRow = false;

      // 1) Sicheres Signal: Kombinations-Referenz vorhanden
      if ($combinationRef !== null && $combinationRef !== '') {
        $isVariationRow = true;
      }

      // 2) Fallback: Varianten-typische Spalten befüllt (auch wenn Referenz leer ist)
      if ($isVariationRow === false) {
        $variationSignals = [
          'Kombination EAN13',
          'Kombinationsmenge',
          'Kombinations-Referenz', // falls Header-Variante/Whitespace
          'Attribute Group: Farbe',
          'Attribute Group: Schlingenlänge | Farbe',
        ];

        foreach ($variationSignals as $sig) {
          $v = $this->cell($assoc, [$sig]);
          $v = is_string($v) ? trim($v) : (is_numeric($v) ? (string) $v : null);

          if ($v !== null && $v !== '') {
            $isVariationRow = true;
            break;
          }
        }
      }

      if ($isVariationRow === true) {
        $combinationIdSafe = ($combinationId !== null && $combinationId !== '')
          ? (string) $combinationId
          : (($combinationRef !== null && $combinationRef !== '') ? (string) $combinationRef : '');

        if ($combinationIdSafe !== '') {
          $this->upsertVariation($product, $assoc, $combinationIdSafe);
          $importedVariations++;
        }
      }

      if ($this->runId && ($importedVariations % 100 === 0)) {
        ImportRun::whereKey($this->runId)->update([
          'processed_rows' => $importedVariations,
        ]);
      }
    }

    if ($this->runId) {
      ImportRun::whereKey($this->runId)->update([
        'processed_rows' => $importedVariations,
      ]);
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
   * Upsertet eine Variante anhand der "Kombinations-Referenz" (interne Artikelnummer).
   * Fallback: "Kombination-ID".
   */
  protected function upsertVariation(Product $product, array $row, string $combinationId): void
  {
    $payload = $this->mapVariationPayload($row);

    $variationRef = $this->cell($row, ['Kombinations-Referenz']);
    $variationRef = is_string($variationRef) ? trim($variationRef) : (is_numeric($variationRef) ? (string) $variationRef : null);

    // SKU bevorzugt aus Kombinations-Referenz (z. B. 400/12-B), sonst Fallback auf ID
    $sku = ($variationRef !== null && $variationRef !== '') ? $variationRef : $combinationId;

    ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $sku],
      $this->filterExistingColumns(ProductVariation::class, $payload)
    );

    // Kombinations-ID als Meta (damit wir sie trotzdem haben)
    $product->meta()->updateOrCreate(
      [
        'scope' => 'variation',
        'key' => 'aliens_combination_id',
        // Optional: wenn du variation_id sauber setzen willst, holen wir erst die Variation
        'variation_id' => null,
      ],
      ['value' => $combinationId]
    );

    // Optional: Kombinations-Referenz ebenfalls als Meta (falls SKU später umgestellt wird)
    if ($variationRef !== null && $variationRef !== '') {
      $product->meta()->updateOrCreate(
        [
          'scope' => 'variation',
          'key' => 'aliens_combination_reference',
          'variation_id' => null,
        ],
        ['value' => $variationRef]
      );
    }
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

  private function upsertProductFeaturesMeta(Product $product, array $assoc): void
  {
    // Optional: Whitelist (empfohlen)
    $allowed = [
      'Normen',
      'Typ',
      'Material',
      'Farbe',

      'Festigkeit / Bruchlast / Belastbarkeit [kN]',
      'Mindestbruchlast [kN]',
      'Mindestbruchlast geschlossen [kN]',
      'Mindestbruchlast offen [kN]',
      'Mindestbruchlast quer [kN]',
      'Mindestbruchlast längs [kN]',
      'Lastaufnahme [kN]',
      'Max. Fangstoß [kN]',
      'Anzahl Normstürze [UIAA]',
      'Durchmesser [mm]',
    ];

    foreach ($assoc as $colName => $raw) {
      if (!is_string($colName)) {
        continue;
      }

      // Header normalisieren (BOM/Whitespace/Sonderzeichen)
      $col = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $colName) ?? $colName;
      $col = trim($col);
      $col = preg_replace('/\s+/u', ' ', $col) ?? $col;
      $col = str_replace('Feature :', 'Feature:', $col);

      if (!str_starts_with($col, 'Feature:')) {
        continue;
      }

      $val = is_string($raw) ? trim($raw) : (string) $raw;
      $val = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $val) ?? $val;
      $val = trim($val);

      if ($val === '') {
        continue;
      }

      $featureName = trim(substr($col, strlen('Feature:')));
      $featureName = preg_replace('/\s+/u', ' ', $featureName) ?? $featureName;
      $featureName = trim($featureName);

      if ($featureName === '') {
        continue;
      }

      // Whitelist anwenden (wenn du erstmal alles willst: diesen Block entfernen)
      if (!in_array($featureName, $allowed, true)) {
        continue;
      }

      $metaKey = 'feature.' . Str::slug($featureName, '_');

      ProductMeta::updateOrCreate(
        [
          'product_id' => $product->id,
          'variation_id' => null,
          'scope' => 'product',
          'key' => $metaKey,
        ],
        [
          'value' => $val,
        ]
      );
    }
  }
}
