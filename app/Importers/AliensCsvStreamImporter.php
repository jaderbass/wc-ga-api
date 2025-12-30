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

    $currentProductId = null;
    $currentProduct = null;

    foreach ($rows as $row) {
      $rowIndex++;

      // Header-Zeile überspringen
      if ($rowIndex === 0) {
        continue;
      }

      $assoc = $this->combineRow($headers, $row);

      // Parent-Kontext + Feature-only + Varianten-Zeilen korrekt
      $productId = $this->cell($assoc, ['Produkt-ID']);

      $combinationId  = $this->cell($assoc, ['Kombination-ID']);
      $combinationRef = $this->cell($assoc, ['Kombinations-Referenz']);

      $featureName     = $this->cell($assoc, ['Feature Name']);
      $featureValue    = $this->cell($assoc, ['Feature Value']);
      $featurePosition = $this->cell($assoc, ['Feature Position']);

      // 1) Parent-Produkt setzen/merken (nur wenn Produkt-ID befüllt ist)
      if ($productId !== null && $productId !== '') {
        $currentProductId = (string) $productId;

        $currentProduct = $this->getOrUpsertProduct(
          $assoc,
          $currentProductId,
          $productCache,
          $productCacheOrder,
          $productCacheMax,
          $importedProducts
        );
      }

      // Ohne aktives Parent können wir nichts zuordnen
      if ($currentProduct === null) {
        continue;
      }

      // 2) Features immer übernehmen (Hauptzeile + Feature-only Zeilen)
      $this->upsertProductFeaturesMeta($currentProduct, $assoc);

      // 3) Feature-only Zeile? (nur Feature Name/Value/Position, keine Kombi-Daten) -> keine Variation
      $isFeatureOnlyRow =
        (($featureName ?? '') !== '' || ($featureValue ?? '') !== '' || ($featurePosition ?? '') !== '')
        && (($combinationId ?? '') === '')
        && (($combinationRef ?? '') === '');

      if ($isFeatureOnlyRow) {
        continue;
      }

      // 4) Varianten-Zeile: Kombinations-Referenz (oder ID) vorhanden -> Variation upsert
      $hasVariationIdentity = (($combinationRef ?? '') !== '' || ($combinationId ?? '') !== '');

      if ($hasVariationIdentity) {
        $combinationIdSafe = (($combinationId ?? '') !== '')
          ? (string) $combinationId
          : (string) $combinationRef;

        $this->upsertVariation($currentProduct, $assoc, $combinationIdSafe);
        $importedVariations++;
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

    // Base-Referenz aus Kombinations-Referenz ableiten ---
    $baseRef = null;

    if ($variationRef !== null && $variationRef !== '') {
      $baseRef = str_contains($variationRef, '-') ? explode('-', $variationRef, 2)[0] : $variationRef;
      $baseRef = trim($baseRef);
    }

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

    // Base-Referenz als Meta speichern ---
    if ($baseRef !== null && $baseRef !== '') {
      $product->meta()->updateOrCreate(
        [
          'scope' => 'variation',
          'key' => 'aliens_variation_base_reference',
          'variation_id' => null,
        ],
        ['value' => $baseRef]
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

    // Beginn Einfügen
    if (isset($out['description'])) {
      $out['description'] = $this->normalizeAliensHtml((string) $out['description']);
    }

    if (isset($out['short_description'])) {
      $out['short_description'] = $this->normalizeAliensHtml((string) $out['short_description']);
    }
    // Ende Einfügen


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

    // --- Beginn Einfügen: Attribute Group:* in attributes_json übernehmen ---
    $attrs = [];

    foreach ($row as $k => $v) {
      if (!is_string($k)) {
        continue;
      }

      $key = trim($k);
      if (!str_starts_with($key, 'Attribute Group:')) {
        continue;
      }

      $val = is_string($v) ? trim($v) : (is_numeric($v) ? (string) $v : null);
      if ($val === null || $val === '') {
        continue;
      }

      $attrs[$key] = $val;
    }

    if ($attrs !== []) {
      $out['attributes_json'] = $attrs;
    }
    // --- Ende Einfügen ---


    return $out;
  }

  // cell() tolerant für unique header suffixe (__2, __3, ...) ---
  protected function cell(array $row, array $candidates): ?string
  {
    // 1) exakte Matches (wie bisher)
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

    // 2) Fallback: unique header suffixe (candidate__2, candidate__3, ...)
    foreach ($candidates as $candidate) {
      foreach ($row as $k => $val) {
        if (!is_string($k)) {
          continue;
        }

        if (!str_starts_with($k, $candidate . '__')) {
          continue;
        }

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

  // Beginn Einfügen
  private function normalizeAliensHtml(?string $html): ?string
  {
    if (!$html) {
      return $html;
    }

    // 1) Die "Nummern-Bildchen"-Boxen in einen Token umwandeln
    $token = '<!--ALIEN_STEP-->';
    $pattern = '~<div[^>]*>\s*<a[^>]*>\s*<img[^>]*Individuelle_Nummerierung\.png[^>]*>\s*</a>\s*</div>~i';

    $htmlWithTokens = preg_replace($pattern, $token, $html);

    if (!$htmlWithTokens || !str_contains($htmlWithTokens, $token)) {
      return $html;
    }

    // 1b) CMS-Icon-Boxen (z.B. Keylock.png) in Text-Links umwandeln (kein <img> mehr)
    $iconPattern = '~<div[^>]*>\s*<a\s+href="([^"]+)"[^>]*>\s*<img[^>]*src="[^"]*/img/cms/([^"/]+)\.png"[^>]*?(?:title="([^"]*)")?[^>]*>\s*</a>\s*</div>~i';

    $htmlWithTokens = preg_replace_callback($iconPattern, static function (array $m): string {
      $href  = $m[1] ?? '#';
      $file  = $m[2] ?? 'icon';
      $title = $m[3] ?? '';

      $label = trim($title) !== '' ? trim($title) : $file;

      // rel noopener für target=_blank
      return '<a class="alien-icon" href="' . $href . '" target="_blank" rel="noopener noreferrer">' . e($label) . '</a>';
    }, $htmlWithTokens);

    // 2) Alles nach dem ersten Token als "Step"-Blöcke wrappen
    $parts  = explode($token, $htmlWithTokens);
    $before = array_shift($parts);

    $steps = [];
    foreach ($parts as $part) {
      $part = trim($part);
      if ($part === '') {
        continue;
      }
      $steps[] = $part;
    }

    if ($steps === []) {
      // Token war da, aber kein Inhalt dahinter
      return $before;
    }

    $wrappedSteps = '<div class="alien-steps">'
      . implode('', array_map(
        static fn(string $step) => '<div class="alien-step">' . $step . '</div>',
        $steps
      ))
      . '</div>';

    return $before . $wrappedSteps;
  }
  // Ende Einfügen

}
