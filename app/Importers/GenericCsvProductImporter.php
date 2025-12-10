<?php

namespace App\Importers;

use App\Importers\Contracts\CsvImporterContract;
use App\Support\ImportLog;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class GenericCsvProductImporter
 *
 * Importiert Produktdaten und Produktvarianten aus einer CSV-Datei anhand eines konfigurierbaren Mappings.
 * Unterstützt Gruppierung nach Hauptprodukt und automatische Zuordnung von Farb- und Größenvarianten.
 *
 * Erwartet eine Mapping-Datei unter config/import_mappings/<mappingName>.php.
 */
class GenericCsvProductImporter implements CsvImporterContract
{
  protected array $mapping;

  /**
   * Initialisiert den Importer mit dem spezifischen Mapping und der Hersteller-ID.
   *
   * @param string $mappingFile Der Name der Mapping-Datei (ohne .php), die unter `config/import_mappings/` liegt.
   * @param int|null $manufacturerId Die ID des Herstellers, dem die importierten Produkte zugeordnet werden.
   */
  public function __construct(
    protected ?string $mappingFile = null,
    protected ?int $manufacturerId = null
  ) {
    // ❌ temporäre Edelrid-Sperre entfernen
    // ✅ Mapping robust laden (aus config() ODER Datei)
    if ($this->mappingFile === null) {
      throw new \RuntimeException('GenericCsvProductImporter benötigt $mappingFile (z. B. "petzl", "edelrid").');
    }

    $this->mapping = $this->loadMapping($this->mappingFile);
  }

  /**
   * Ermittelt die Author-ID für diesen Importlauf.
   *
   * Aktuell:
   * - verwendet den eingeloggten Benutzer (auth()->id()).
   * Später könnte hier noch eine explizite Zuweisung ergänzt werden.
   */
  protected function resolveAuthorId(): ?int
  {
    $user = Auth::user();

    return $user?->id;
  }

  /**
   * Importiert eine CSV-Datei, erkennt das Hersteller-Mapping automatisch an den Headern
   * und gruppiert die Zeilen robust nach dem Mapping.
   *
   * Auto-Mapping:
   * - Erkennt Edelrid an „Artikelbezeichnung“/„Artikelnummer“ (deutsche Header)
   * - Erkennt Petzl an „Product name“/„Reference“ (englische Header)
   * - Überschreibt nur dann das Mapping, wenn es noch nicht gesetzt ist
   *
   * @param string $filePath
   * @return void
   */
  public function import(string $filePath): void
  {
    $csv = \League\Csv\Reader::createFromPath($filePath, 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);
    $csv->skipEmptyRecords();

    // 1) Header + Records lesen
    $records = iterator_to_array($csv->getRecords());
    $headers = $csv->getHeader();

    ImportLog::debug('CSV Header', [
      'count'   => count($headers),
      'sample'  => array_slice($headers, 0, 5),
    ]);


    ImportLog::debug('Active mapping snapshot', [
      'product_keys'   => array_keys($this->mapping['product'] ?? []),
      'variation_keys' => array_keys($this->mapping['variation'] ?? []),
      'vf_keys'        => array_keys($this->mapping['variation_fields'] ?? []),
    ]);


    // 2) Header normalisieren (unsichtbare Zeichen entfernen, trimmen, lowercased)
    $normalizeHeader = function (string $h): string {
      $s = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $h) ?? $h; // Steuerz./NBSP/BOM
      $s = preg_replace('/\s+/u', ' ', $s) ?? $s; // Mehrfachspaces
      return mb_strtolower(trim($s));
    };
    $normalizedHeaders = array_map($normalizeHeader, $headers);

    // 3) Mapping automatisch wählen, falls noch nicht gesetzt
    //    (z. B. wenn ein generischer Importer genutzt wird)
    if (empty($this->mapping) || !is_array($this->mapping)) {
      $isEdelrid = in_array('artikelbezeichnung', $normalizedHeaders, true)
        || in_array('artikelnummer', $normalizedHeaders, true);
      $isPetzl   = in_array('product name', $normalizedHeaders, true)
        || in_array('reference', $normalizedHeaders, true);

      if ($isEdelrid) {
        $this->mapping = config('import_mappings.edelrid');
        ImportLog::debug('Auto-selected mapping', ['mapping' => 'edelrid']);
      } elseif ($isPetzl) {
        $this->mapping = config('import_mappings.petzl');
        ImportLog::debug('Auto-selected mapping', ['mapping' => 'petzl']);
      } else {
        // falls nichts erkannt wird, Mapping so lassen (oder optional defaulten)
        ImportLog::debug('Auto-selected mapping', ['mapping' => 'unchanged']);
      }
    }

    // 4) Alle Zellen trimmen
    $normalized = collect($records)->map(function (array $row) {
      foreach ($row as $k => $v) {
        $row[$k] = is_string($v) ? trim($v) : $v;
      }
      return $row;
    });

    // 5) Gruppierung robust (group_by kann String oder Array sein)
    $groupBy = $this->mapping['group_by'] ?? null;
    $groupByCols = is_array($groupBy)
      ? array_values($groupBy)
      : ((is_string($groupBy) && $groupBy !== '') ? [$groupBy] : []);

    // Fallbacks für Edelrid (falls Mapping unvollständig)
    $fallbackCols = ['Artikelbezeichnung', 'Artikelnummer'];

    // Helfer: sauberer Text (entfernt Steuerz. & normalisiert Spaces)
    $clean = function (?string $value): string {
      if ($value === null) return '';
      $s = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $value) ?? $value;
      $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
      return trim($s);
    };

    // Helfer: erster nicht-leerer Wert aus Kandidaten
    $firstNonEmpty = function (array $row, array $candidates) use ($clean): string {
      if (empty($candidates)) return '';
      // Map normalisierte Header → Original-Key der aktuellen Row
      $map = [];
      foreach (array_keys($row) as $key) {
        $norm = mb_strtolower($clean((string)$key));
        $map[$norm] = $key;
      }
      foreach ($candidates as $cand) {
        $normCand = mb_strtolower($clean($cand));
        if (isset($map[$normCand])) {
          $val = $clean((string)($row[$map[$normCand]] ?? ''));
          if ($val !== '') return $val;
        }
        // direkter Zugriff als Fallback
        if (array_key_exists($cand, $row)) {
          $val = $clean((string)($row[$cand] ?? ''));
          if ($val !== '') return $val;
        }
      }
      return '';
    };

    // Index für Notnagel-Gruppierung
    $indexed = $normalized->values()->map(function (array $row, int $i) {
      $row['__row_index'] = $i;
      return $row;
    });

    $grouped = $indexed->groupBy(function (array $row) use ($groupByCols, $fallbackCols, $firstNonEmpty, $clean): string {
      if (!empty($groupByCols)) {
        $val = $firstNonEmpty($row, $groupByCols);
        if ($val !== '') return $clean($val);
      }
      $val = $firstNonEmpty($row, $fallbackCols);
      if ($val !== '') return $clean($val);
      $i = $row['__row_index'] ?? 'x';
      return '__ROW__:' . $i;
    });

    ImportLog::debug('CSV group keys (normalized)', [
      'count_groups' => $grouped->count(),
    ]);

    // 6) Gruppen importieren (Transaktion)
    DB::transaction(function () use ($grouped) {
      foreach ($grouped as $groupKey => $rows) {
        ImportLog::debug('Import group', ['groupKey' => $groupKey, 'rows' => $rows->count()]);
        $this->importProductGroup($groupKey, $rows);
      }
    });
  }

  /**
   * Liefert den ersten nicht-leeren Zellwert aus $row für eine Spalten-Spezifikation.
   * $spec kann 'Spaltenname' oder ['Alt1','Alt2',...] sein.
   */
  /**
   * Liefert den ersten nicht-leeren Zellwert aus $row für eine Spalten-Spezifikation.
   * $spec kann 'Spaltenname' oder ['Alt1','Alt2', …] sein.
   *
   * @param  array<string,mixed>      $row
   * @param  string|array<int,string> $spec
   * @return string|null
   */
  private function cell(array $row, string|array $spec): ?string
  {
    if (is_array($spec)) {
      foreach ($spec as $col) {
        if (array_key_exists($col, $row) && trim((string) $row[$col]) !== '') {
          return trim((string) $row[$col]);
        }
      }
      return null;
    }

    return array_key_exists($spec, $row) && trim((string) $row[$spec]) !== ''
      ? trim((string) $row[$spec])
      : null;
  }

  /**
   * Importiert eine Produkt-Gruppe (Hauptprodukt + Varianten).
   *
   * - Spaltenzugriffe robust via firstNonEmptyFromRow() (Umlaute/NBSP tolerant)
   * - Short-Description-Fallback aus Description
   * - Upsert schema-robust (nur existierende Spalten) + forceFill()
   * - Klare Diagnose-Logs: welche Felder werden geschrieben / gefiltert / Variantenanzahl
   *
   * @param string                                $groupKey
   * @param \Illuminate\Support\Collection<int,array<string,mixed>> $rows
   * @return void
   */
  protected function importProductGroup(string $groupKey, \Illuminate\Support\Collection $rows): void
  {

    // reference kann String oder Array sein
    $referenceKey  = $this->mapping['reference'] ?? null;
    $referenceCols = is_array($referenceKey)
      ? $referenceKey
      : ((is_string($referenceKey) && $referenceKey !== '') ? [$referenceKey] : []);

    // Fallbacks: Edelrid 'Artikelnummer', generisch 'Reference'
    if (empty($referenceCols) || $referenceCols === ['Reference']) {
      $referenceCols = ['Artikelnummer', 'Reference'];
    }

    /**
     * Erzeuge die Produkt-Payload aus dem Mapping.
     * Nutzt primär das 'fields'-Mapping, erweitert um 'product' als Override.
     * 'product' überschreibt ggf. Einträge aus 'fields'.
     */
    $baseProductMapping = $this->mapping['product'] ?? [];
    $fieldMapping       = $this->mapping['fields'] ?? [];

    // Effektives Produkt-Mapping: zuerst alle Felder, dann explizite Produkt-Felder
    $productMapping = array_merge($fieldMapping, $baseProductMapping);
    $productPayload = [];

    ImportLog::debug('Edelrid product mapping keys', [
      'keys' => array_keys($productMapping),
    ]);

    foreach ($productMapping as $dbField => $csvColumn) {
      $candidates = is_array($csvColumn) ? $csvColumn : [$csvColumn];

      // erste Zeile in der Gruppe mit nicht-leerem Wert (robust) finden
      $sourceRow = $rows->first(function (array $row) use ($candidates) {
        $v = $this->firstNonEmptyFromRow($row, $candidates);
        return $v !== null && trim((string)$v) !== '';
      });

      $resolved = null;
      if ($sourceRow) {
        $val = $this->firstNonEmptyFromRow($sourceRow, $candidates);
        if ($val !== null && trim((string)$val) !== '') {
          $resolved = trim((string)$val);
        }
      }

      // Ausgangswert für das Feld
      $finalForField = $resolved;

      // Optional: Transform für dieses Feld anwenden
      if (!empty($this->mapping['transforms'][$dbField])) {
        $transform = $this->mapping['transforms'][$dbField];

        // Variante: [ClassName::class, 'method']
        if (is_array($transform) && count($transform) === 2) {
          [$class, $method] = $transform;
          if (class_exists($class) && method_exists($class, $method)) {
            $finalForField = $class::$method($resolved, $sourceRow ?? []);
          }
        }

        // Variante: Closure
        if ($transform instanceof \Closure) {
          $finalForField = $transform($resolved, $sourceRow ?? []);
        }
      }

      // Transform-Ergebnis in Payload schreiben
      if (is_array($finalForField)) {
        // Unterscheide numerische Arrays (z. B. Liste von Bild-URLs)
        // von assoziativen Arrays (z. B. dimensions_raw + *_mm)
        $keys        = array_keys($finalForField);
        $isSequential = $keys === range(0, count($finalForField) - 1);

        if ($isSequential) {
          // einfache Liste → bleibt auf dem ursprünglichen Feld
          $productPayload[$dbField] = $finalForField;
        } else {
          // assoziatives Array → mehrere Felder in Payload schreiben
          foreach ($finalForField as $key => $value) {
            $productPayload[$key] = $value;
          }
        }
      } elseif ($finalForField !== null && trim((string) $finalForField) !== '') {
        $productPayload[$dbField] = $finalForField;
      }

      // Diagnose-Log: zeigt pro Feld, welche Kandidaten probiert wurden und was rauskam
      ImportLog::debug('Mapping check', [
        'group'      => $groupKey,
        'field'      => $dbField,
        'candidates' => $candidates,
        'resolved'   => $resolved, // Originalwert vor Transform
      ]);
    }



    // // Debug pro Feld
    // ImportLog::debug('Mapping check', [
    //   'field' => $dbField,
    //   'candidates' => $candidates,
    //   'resolved' => $productPayload[$dbField] ?? null,
    // ]);



    // Fallback: Shortdescription aus Description (max 255, HTML raus)
    if (
      (!array_key_exists('short_description', $productPayload) ||
        trim((string)($productPayload['short_description'] ?? '')) === '')
      && !empty($productPayload['description'])
    ) {
      $productPayload['short_description'] = \Illuminate\Support\Str::limit(
        strip_tags((string) $productPayload['description']),
        255
      );
    }

    // Name/Slug/Feste Werte
    $name = $productPayload['product_name'] ?? trim($groupKey) ?: 'Unnamed Product';
    $slug = \Illuminate\Support\Str::slug($name) ?: \Illuminate\Support\Str::slug('product-' . uniqid());

    $finalProductPayload = array_merge($productPayload, [
      'product_name'    => $name,
      // Achtung: diese Keys schreiben wir nur, wenn Spalten existieren (siehe unten)
      'product_type'    => 'variable',
      'manufacturer_id' => $this->manufacturerId,
      'status'          => 'draft',
    ]);

    $authorId = $this->resolveAuthorId();

    // --- Upsert schema-robust + Diagnose ---
    /** @var \App\Models\Product $tmpModel */
    $tmpModel   = app(\App\Models\Product::class);
    $tableName  = $tmpModel->getTable();
    $columns    = \Illuminate\Support\Facades\Schema::getColumnListing($tableName);
    $columnSet  = array_flip($columns);

    // Welche Felder KÖNNEN wir wirklich schreiben?
    $writablePayload = array_intersect_key($finalProductPayload, $columnSet);
    $droppedKeys     = array_diff(array_keys($finalProductPayload), array_keys($writablePayload));

    ImportLog::debug('Product payload before upsert', [
      'group'           => $groupKey,
      'slug'            => $slug,
      'final_payload'   => $finalProductPayload,
      'writable_payload'=> $writablePayload,
      'dropped_keys'    => $droppedKeys,
    ]);

    if (($finalProductPayload['product_number'] ?? null) === '717620003600') {
      ImportLog::debug('DEBUG Bud payload', [
        'group'         => $groupKey,
        'final_payload' => $finalProductPayload,
      ]);
    }

    // Basisdaten für Create (nur vorhandene Spalten)
    $baseCreate = [];
    foreach (
      [
        'slug'            => $slug,
        'manufacturer_id' => $this->manufacturerId,
        'product_name'    => $name,
        'product_type'    => $finalProductPayload['product_type'] ?? null,
        'status'          => $finalProductPayload['status'] ?? null,
        'author_id'       => $authorId,
      ] as $col => $val
    ) {
      if (isset($columnSet[$col]) && $val !== null) {
        $baseCreate[$col] = $val;
      }
    }

    // Produkt holen/erstellen
    $query = \App\Models\Product::query()->where('slug', $slug);
    #if ($this->manufacturerId) {
    #  $query->where('manufacturer_id', $this->manufacturerId);
    #}
    $product = $query->first();

    if ($product) {
      $product->forceFill($writablePayload)->save();
    } else {
      $product = \App\Models\Product::create($baseCreate);
      if (!empty($writablePayload)) {
        $product->forceFill($writablePayload)->save();
      }
    }

    Log::info('Product upserted', [
      'id'              => $product->id,
      'name'            => $product->product_name,
      'product_number'  => $product->product_number,
      'ean'             => $product->external_url,
    ]);

    // --- Varianten importieren ---
    $skipped = 0;
    $imported = 0;
    $loggedRefDiag = false;

    foreach ($rows as $row) {
      $ref = $this->firstNonEmptyFromRow($row, $referenceCols);
      $ref = $ref !== null ? trim((string) $ref) : '';

      if (!$loggedRefDiag) {
        /**
         * ! Mit Flag !!!
         */
        ImportLog::debug('Reference detection (group)', [
          'group'          => $groupKey,
          'reference_cols' => $referenceCols,
          'sample_ref'     => $ref,
        ]);
        $loggedRefDiag = true;
      }

      if ($ref === '') {
        $skipped++;
        continue;
      }

      // Variante anlegen/aktualisieren (bestehende Logik)
      $this->importVariation($product, $row);
      $imported++;
    }

    // Nachzählung (falls Relation vorhanden)
    try {
      $relCount = method_exists($product, 'variations') ? $product->variations()->count() : null;
    } catch (\Throwable $e) {
      $relCount = null;
    }

    if ($skipped > 0 || $imported > 0) {
      Log::info('Variation summary', [
        'group'          => $groupKey,
        'imported'       => $imported,
        'skipped'        => $skipped,
        'relation_count' => $relCount,
      ]);
    }
  }

  /**
   * Importiert oder aktualisiert eine einzelne Produktvariante.
   *
   * Nutzt die SKU (Referenz) aus der CSV-Zeile, um eine Variante zu identifizieren.
   * Führt ein `updateOrCreate` für die Variante durch und stößt die Zuweisung
   * der Attribute (z.B. Farbe, Größe) an.
   *
   * @param Product $product Das übergeordnete Hauptprodukt.
   * @param array   $row     Die CSV-Zeile, die die Daten der Variante enthält.
   * @return void
   */
  protected function importVariation(Product $product, array $row)
  {
    // reference kann String ODER Array sein -> robust per cell()
    $referenceSpec = $this->mapping['reference'] ?? 'Reference';
    $ref = $this->cell($row, is_array($referenceSpec) ? $referenceSpec : [$referenceSpec]);
    $ref = $ref !== null ? trim((string) $ref) : null;

    if ($ref === null || $ref === '') {
      return; // ohne SKU keine Variante
    }

    // 1) Payload aus variation_fields
    // Unterstützte Formate:
    // - 'field' => 'CSV-Spalte'
    // - 'field' => ['CSV-Spalte 1', 'CSV-Spalte 2']
    // - 'field' => [
    //       'columns'   => 'CSV-Spalte' ODER ['CSV-Spalte 1', 'CSV-Spalte 2'],
    //       'transform' => Closure ODER [Class::class, 'method'],
    //   ]
    $variationPayload = [];
    $variationFieldsMapping = $this->mapping['variation_fields'] ?? [];

    foreach ($variationFieldsMapping as $dbField => $csvSpec) {
      $rawValue = null;

      // Neue strukturierte Variante: ['columns' => ..., 'transform' => ...]
      if (is_array($csvSpec) && (array_key_exists('columns', $csvSpec) || array_key_exists('column', $csvSpec))) {
        $cols = $csvSpec['columns'] ?? $csvSpec['column'];
        $candidates = is_array($cols) ? $cols : [$cols];
        $rawValue = $this->cell($row, $candidates);
      } else {
        // Alte Variante: String ODER Array von Strings
        $candidates = is_array($csvSpec) ? $csvSpec : [$csvSpec];
        $rawValue = $this->cell($row, $candidates);
      }

      if ($rawValue === null || $rawValue === '') {
        continue;
      }

      // Basiswert ggf. trimmen, wenn String
      $finalValue = is_string($rawValue) ? trim($rawValue) : $rawValue;

      // Optional: Transform aus dem Mapping anwenden (nur bei strukturiertem Mapping)
      if (is_array($csvSpec) && array_key_exists('transform', $csvSpec)) {
        $transform = $csvSpec['transform'];

        // Variante: [ClassName::class, 'method']
        if (is_array($transform) && count($transform) === 2) {
          [$class, $method] = $transform;
          if (class_exists($class) && method_exists($class, $method)) {
            $finalValue = $class::$method($finalValue, $row);
          }
        }

        // Variante: Closure
        if ($transform instanceof \Closure) {
          $finalValue = $transform($finalValue, $row);
        }
      }

      $variationPayload[$dbField] = $finalValue;
    }

    // 2) Variante erstellen oder aktualisieren (bestehende Logik beibehalten)
    $variation = ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $ref],
      $variationPayload
    );

    // Attribute zuweisen
    $this->handleVariationAttributes($variation, $row);
  }

  /**
   * Liest Varianten-Attribute aus der CSV-Zeile und verknüpft deren Werte
   * mit der Variante (Pivot-Tabelle).
   *
   * @param  \App\Models\ProductVariation  $variation
   * @param  array<string,mixed>           $row
   * @return void
   */
  protected function handleVariationAttributes(\App\Models\ProductVariation $variation, array $row): void
  {
    $attributeValueIds = [];
    $variationMapping  = $this->mapping['variation'] ?? [];

    foreach ($variationMapping as $attributeName => $csvSpec) {
      $value = $this->cell($row, $csvSpec);
      if ($value === null || $value === '') {
        continue;
      }

      $attributeDisplayName = (string) $attributeName;
      $attributeSlug        = \Illuminate\Support\Str::slug($attributeDisplayName);

      $attribute = \App\Models\ProductAttribute::firstOrCreate(
        ['slug' => $attributeSlug],
        ['name' => $attributeDisplayName]
      );

      $attributeValue = \App\Models\ProductAttributeValue::firstOrCreate(
        ['attribute_id' => $attribute->id, 'slug' => \Illuminate\Support\Str::slug($value)],
        ['value' => $value]
      );

      $attributeValueIds[] = $attributeValue->id;
    }

    if (! empty($attributeValueIds)) {
      $variation->attributeValues()->sync($attributeValueIds);
    }
  }



  /**
   * Normalisiert CSV-Headernamen robust:
   * - entfernt Steuerzeichen / NBSP / BOM
   * - reduziert Mehrfach-Spaces
   * - trimmt
   * - lowercased für case-insensitive Vergleiche
   *
   * @param string $header
   * @return string
   */
  protected function normalizeHeader(string $header): string
  {
    // unsichtbare/Steuerzeichen (inkl. NBSP \xA0 und BOM \xFEFF) entfernen
    $s = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $header) ?? $header;
    // Mehrfach-Spaces vereinheitlichen
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return mb_strtolower(trim($s));
  }

  /**
   * Liefert den ersten nicht-leeren Zellwert aus einer CSV-Zeile anhand einer Kandidatenliste
   * von Spaltennamen. Berücksichtigt unterschiedliche Schreibweisen/Umlaute/Spaces dank
   * Header-Normalisierung.
   *
   * Beispiel:
   *   firstNonEmptyFromRow($row, ['Farbe Bezeichnung','Farb-Code','Farbcode','Farbe'])
   *
   * @param array<string,mixed> $row         Assoziatives Array (CSV-Zeile)
   * @param array<int,string>   $candidates  Mögliche Spaltennamen (in Priorität)
   * @return ?string                         Erster gefundener, getrimmter Wert oder null
   */
  protected function firstNonEmptyFromRow(array $row, array $candidates): ?string
  {
    if (empty($row) || empty($candidates)) {
      return null;
    }

    // Map: normalisierter Header -> Original-Header
    $keyMap = [];
    foreach (array_keys($row) as $key) {
      $keyMap[$this->normalizeHeader((string)$key)] = $key;
    }

    foreach ($candidates as $cand) {
      $normCand = $this->normalizeHeader((string)$cand);

      // 1) bevorzugt über normalisierte Header-Map
      if (isset($keyMap[$normCand])) {
        $val = $row[$keyMap[$normCand]] ?? null;
        if (is_string($val)) {
          $val = trim($val);
          // erneut unsichtbare Zeichen entfernen
          $val = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $val) ?? $val;
        }
        if ($val !== null && $val !== '') {
          return (string)$val;
        }
      }

      // 2) Fallback: direkter Zugriff (falls Key exakt passt)
      if (array_key_exists($cand, $row)) {
        $val = $row[$cand];
        if (is_string($val)) {
          $val = trim($val);
          $val = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $val) ?? $val;
        }
        if ($val !== null && $val !== '') {
          return (string)$val;
        }
      }
    }

    return null;
  }

  /**
   * Lädt das Mapping entweder aus config('import_mappings.<name>')
   * oder aus config/import_mappings/<name>.php (Datei muss ein Array returnen).
   *
   * @throws \RuntimeException wenn nichts gefunden.
   */
  protected function loadMapping(string $name): array
  {
    $fromConfig = config("import_mappings.{$name}");
    if (is_array($fromConfig)) {
      return $fromConfig;
    }

    $path = base_path("config/import_mappings/{$name}.php");
    if (is_file($path)) {
      $map = require $path;
      if (! is_array($map)) {
        throw new \RuntimeException("Mapping file {$path} must return an array.");
      }
      return $map;
    }

    throw new \RuntimeException("Mapping '{$name}' not found via config() or file {$path}");
  }
}
