<?php

namespace App\Importers;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Importers\Contracts\CsvImporterContract;

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
    protected string $mappingFile,
    protected ?int $manufacturerId = null
  ) {
    if (($this->manufacturerId ?? null) === 6 /* Edelrid-ID bei dir */) {
      throw new \RuntimeException('Edelrid darf nicht über GenericCsvProductImporter laufen.');
    }
    $this->mapping = config("import_mappings.$mappingFile");
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

    Log::debug('CSV Header', ['headers' => $headers, 'count' => count($records)]);

    Log::debug('Active mapping snapshot', [
      'product'          => $this->mapping['product'] ?? null,
      'variation_fields' => $this->mapping['variation_fields'] ?? null,
      'variation'        => $this->mapping['variation'] ?? null,
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
        Log::debug('Auto-selected mapping', ['mapping' => 'edelrid']);
      } elseif ($isPetzl) {
        $this->mapping = config('import_mappings.petzl');
        Log::debug('Auto-selected mapping', ['mapping' => 'petzl']);
      } else {
        // falls nichts erkannt wird, Mapping so lassen (oder optional defaulten)
        Log::debug('Auto-selected mapping', ['mapping' => 'unchanged']);
      }
    } else {
      Log::debug('Mapping provided by importer', [
        'keys' => array_keys($this->mapping),
      ]);
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

    Log::debug('CSV group keys (normalized)', [
      'count_groups' => $grouped->count(),
      'sample_keys'  => $grouped->keys()->take(5)->all(),
    ]);

    // 6) Gruppen importieren (Transaktion)
    DB::transaction(function () use ($grouped) {
      foreach ($grouped as $groupKey => $rows) {
        Log::debug('Import group', ['groupKey' => $groupKey, 'rows' => $rows->count()]);
        $this->importProductGroup($groupKey, $rows);
      }
    });
  }

  /**
   * Liefert den ersten nicht-leeren Zellwert aus $row für eine Spalten-Spezifikation.
   * $spec kann 'Spaltenname' oder ['Alt1','Alt2',...] sein.
   */
  private function cell(array $row, string|array $spec): ?string
  {
    if (is_array($spec)) {
      foreach ($spec as $col) {
        if (array_key_exists($col, $row) && trim((string)$row[$col]) !== '') {
          return trim((string)$row[$col]);
        }
      }
      return null;
    }

    return array_key_exists($spec, $row) && trim((string)$row[$spec]) !== ''
      ? trim((string)$row[$spec])
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
    Log::debug('Import group', ['groupKey' => $groupKey, 'rows' => $rows->count()]);

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
     * Zusätzlich: Diagnose-Logs je Feld, um zu sehen, welcher Kandidat greift.
     */
    $productMapping = $this->mapping['product'] ?? [];
    $productPayload = [];

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
          $productPayload[$dbField] = $resolved;
        }
      }

      // Diagnose-Log: zeigt pro Feld, welche Kandidaten probiert wurden und was rauskam
      Log::debug('Mapping check', [
        'group'      => $groupKey,
        'field'      => $dbField,
        'candidates' => $candidates,
        'resolved'   => $resolved, // null = kein Treffer
      ]);
    }


    // Debug pro Feld
    Log::debug('Mapping check', [
        'field' => $dbField,
        'candidates' => $candidates,
        'resolved' => $productPayload[$dbField] ?? null,
      ]);
    


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

    // --- Upsert schema-robust + Diagnose ---
    /** @var \App\Models\Product $tmpModel */
    $tmpModel   = app(\App\Models\Product::class);
    $tableName  = $tmpModel->getTable();
    $columns    = \Illuminate\Support\Facades\Schema::getColumnListing($tableName);
    $columnSet  = array_flip($columns);

    // Welche Felder KÖNNEN wir wirklich schreiben?
    $writablePayload = array_intersect_key($finalProductPayload, $columnSet);
    $droppedKeys     = array_diff(array_keys($finalProductPayload), array_keys($writablePayload));

    Log::debug('Product upsert payload (pre-filter)', [
      'group'          => $groupKey,
      'final_keys'     => array_keys($finalProductPayload),
      'writable_keys'  => array_keys($writablePayload),
      'dropped_keys'   => array_values($droppedKeys), // z.B. status, falls Spalte fehlt
      'sample_payload' => array_intersect_key($finalProductPayload, array_flip([
        'product_name',
        'product_number',
        'ean',
        'description',
        'short_description'
      ])),
    ]);

    // Basisdaten für Create (nur vorhandene Spalten)
    $baseCreate = [];
    foreach (
      [
        'slug'            => $slug,
        'manufacturer_id' => $this->manufacturerId,
        'product_name'    => $name,
        'product_type'    => $finalProductPayload['product_type'] ?? null,
        'status'          => $finalProductPayload['status'] ?? null,
      ] as $col => $val
    ) {
      if (isset($columnSet[$col]) && $val !== null) {
        $baseCreate[$col] = $val;
      }
    }

    // Produkt holen/erstellen
    $query = \App\Models\Product::query()->where('slug', $slug);
    if ($this->manufacturerId) {
      $query->where('manufacturer_id', $this->manufacturerId);
    }
    $product = $query->first();

    if ($product) {
      $product->forceFill($writablePayload)->save();
    } else {
      $product = \App\Models\Product::create($baseCreate);
      if (!empty($writablePayload)) {
        $product->forceFill($writablePayload)->save();
      }
    }

    Log::debug('Product upserted', [
      'id'              => $product->id,
      'written_keys'    => array_keys($writablePayload),
      'name'            => $product->product_name,
      // falls Spalten existieren, zeigen wir sie kurz:
      'product_number'  => $product->product_number ?? null,
      'ean'             => $product->ean ?? null,
    ]);

    // --- Varianten importieren ---
    $skipped = 0;
    $imported = 0;
    $loggedRefDiag = false;

    foreach ($rows as $row) {
      $ref = $this->firstNonEmptyFromRow($row, $referenceCols);
      $ref = $ref !== null ? trim((string) $ref) : '';

      if (!$loggedRefDiag) {
        Log::debug('Reference detection (group)', [
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
      Log::debug('Variation summary', [
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
    $referenceKey = $this->mapping['reference'] ?? 'Reference';
    $ref = isset($row[$referenceKey]) ? trim($row[$referenceKey]) : null;
    if ($ref === null || $ref === '') {
      return; // ohne SKU keine Variante
    }

    // 1. Payload für die Variante aus der Mapping-Datei erstellen
    $variationPayload = [];
    $variationFieldsMapping = $this->mapping['variation_fields'] ?? [];
    foreach ($variationFieldsMapping as $dbField => $csvColumn) {
        if (isset($row[$csvColumn])) {
            $variationPayload[$dbField] = trim($row[$csvColumn]);
        }
    }

    // 2. Variante erstellen oder aktualisieren
    $variation = ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $ref], // 👈 Upsert-Key
      $variationPayload
    );

    // 2. Attribute über die neuen Tabellen zuweisen
    $this->handleVariationAttributes($variation, $row);
  }

  /**
   * Erstellt und verknüpft Attribute und deren Werte mit einer Produktvariante.
   *
   * Liest die Attribut-Mappings aus der Konfigurationsdatei (z.B. 'color' => 'Farbe').
   * Erstellt die `ProductAttribute` (z.B. "Farbe") und `ProductAttributeValue` (z.B. "Blau")
   * falls sie noch nicht existieren (`firstOrCreate`).
   * Synchronisiert anschließend die gefundenen/erstellten Attributwerte mit der Variante
   * über die Pivot-Tabelle `product_variation_attribute_value`.
   *
   * @param ProductVariation $variation Die zu bearbeitende Produktvariante.
   * @param array            $row       Die CSV-Zeile mit den Attributwerten.
   * @return void
   */
  protected function handleVariationAttributes(ProductVariation $variation, array $row): void
  {
    $attributeValueIds = [];
    $variationMapping = $this->mapping['variation'] ?? [];

    foreach ($variationMapping as $attributeName => $csvSpec) {
      // csvSpec kann String ODER Array sein → cell() nimmt den ersten nicht-leeren Wert
      $value = $this->cell($row, $csvSpec);

      if ($value === null || $value === '') {
        continue;
      }

      // Verwende den Key aus dem Mapping als Anzeigename (z. B. "Farbe", "Größe")
      $attributeDisplayName = (string) $attributeName;
      $attributeSlug = Str::slug($attributeDisplayName);

      $attribute = ProductAttribute::firstOrCreate(
        ['slug' => $attributeSlug],
        ['name' => $attributeDisplayName]
      );

      $attributeValue = ProductAttributeValue::firstOrCreate(
        ['attribute_id' => $attribute->id, 'slug' => Str::slug($value)],
        ['value' => $value]
      );

      $attributeValueIds[] = $attributeValue->id;
    }

    if (!empty($attributeValueIds)) {
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
}
