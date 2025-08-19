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

/**
 * Class GenericCsvProductImporter
 *
 * Importiert Produktdaten und Produktvarianten aus einer CSV-Datei anhand eines konfigurierbaren Mappings.
 * Unterstützt Gruppierung nach Hauptprodukt und automatische Zuordnung von Farb- und Größenvarianten.
 *
 * Erwartet eine Mapping-Datei unter config/import_mappings/<mappingName>.php.
 */
class GenericCsvProductImporter
{
  protected array $mapping;

  /**
   * Mapping normalisierter Header → originale Header-Keys aus der CSV.
   *
   * Wird zur Laufzeit pro Import befüllt, um Lookups robuster zu machen
   * (z. B. " size" ↔ "size", NBSP etc.).
   *
   * @var array<string,string>
   */
  protected array $__normalizedHeaderKeyMap = [];



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
    $this->mapping = config("import_mappings.$mappingFile");
  }

  /**
   * Führt den Importprozess für die angegebene CSV-Datei aus.
   *
   * Liest die CSV ein, normalisiert Zellwerte und gruppiert die Zeilen robust
   * (Header‑Normalisierung) nach dem in $this->mapping['group_by'] angegebenen Feld.
   *
   * @param string $filePath Der Pfad zur hochgeladenen CSV-Datei.
   * @return void
   */
  public function import(string $filePath): void
  {
    // CSV laden
    $csv = \League\Csv\Reader::createFromPath($filePath, 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);
    $csv->skipEmptyRecords();

    // Records + Header holen
    $records = iterator_to_array($csv->getRecords());
    $headers = $csv->getHeader();

    Log::debug('CSV Header', ['headers' => $headers, 'count' => count($records)]);

    // Zellwerte trimmen (rechte/linke Spaces entfernen)
    $normalized = collect($records)->map(function (array $row) {
      foreach ($row as $k => $v) {
        $row[$k] = is_string($v) ? trim($v) : $v;
      }
      return $row;
    });

    // Header-Normalisierungs-Cache für diesen Importlauf zurücksetzen
    $this->__normalizedHeaderKeyMap = [];

    // Probe: erste Zeile inspizieren (nur Debug)
    if ($normalized->isNotEmpty()) {
      $first = $normalized->first();
      Log::debug('Probe first row (detected keys)', [
        'Artikelbezeichnung' => $this->valueByHeader($first, 'Artikelbezeichnung'),
        'Artikelnummer'      => $this->valueByHeader($first, 'Artikelnummer'),
      ]);
    }

    // Robuste Gruppierung
    $grouped = $this->groupRowsByMapping($normalized);

    Log::debug('CSV group keys (normalized)', [
      'count_groups' => $grouped->count(),
      'sample_keys'  => $grouped->keys()->take(5)->all(),
    ]);

    foreach ($grouped as $groupKey => $groupRows) {
      Log::debug('Import group', ['groupKey' => $groupKey, 'rows' => $groupRows->count()]);
      $this->importProductGroup($groupKey, $groupRows);
    }
  }


  /**
   * Importiert eine Produkt-Gruppe: legt/aktualisiert das Hauptprodukt an
   * und fügt anschließend alle Varianten hinzu.
   *
   * @param string $groupKey                       Der gruppierende Schlüssel (z. B. Produktname)
   * @param \Illuminate\Support\Collection $rows   Alle CSV-Zeilen dieser Gruppe
   * @return void
   */
  protected function importProductGroup(string $groupKey, \Illuminate\Support\Collection $rows): void
  {
    $productMapping = $this->mapping['product'] ?? [];
    $productPayload = [];

    // 1) Produkt-Payload aus dem Mapping zusammenbauen (mit Fallbacks/normalisierten Headern)
    foreach ($productMapping as $dbField => $csvColumnOrList) {
      $columns  = is_array($csvColumnOrList) ? $csvColumnOrList : [$csvColumnOrList];
      $source   = $rows->first(fn(array $row) => $this->firstNonEmptyFromRow($row, $columns) !== null);
      if ($source) {
        $val = $this->firstNonEmptyFromRow($source, $columns);
        if ($val !== null) {
          $productPayload[$dbField] = $val;
        }
      }
    }

    // 2) Produktname bestimmen (niemals aus technischen Keys wie "__ROW__")
    $name = trim((string) ($productPayload['product_name'] ?? $productPayload['name'] ?? ''));
    if ($name === '' || str_starts_with($groupKey, '__')) {
      $firstRow = $rows->first() ?? [];
      $name = $this->firstNonEmptyFromRow($firstRow, ['Artikelbezeichnung', 'Artikelnummer']) ?? $groupKey;
    }

    // 3) Zusätzliche Fallbacks aus der ersten Zeile (falls im Mapping nicht enthalten oder leer)
    $firstRow = $rows->first() ?? [];
    $productPayload += []; // noop für Klarheit

    if (empty($productPayload['product_number'])) {
      $productPayload['product_number'] = $this->firstNonEmptyFromRow($firstRow, ['Artikelnummer']) ?? null;
    }
    if (empty($productPayload['ean'])) {
      $productPayload['ean'] = $this->firstNonEmptyFromRow($firstRow, ['EAN']) ?? null;
    }
    if (!array_key_exists('description', $productPayload) || $productPayload['description'] === null || $productPayload['description'] === '') {
      $productPayload['description'] = $this->firstNonEmptyFromRow($firstRow, ['Produkt-Text']) ?? null;
    }
    if (!array_key_exists('short_description', $productPayload) || $productPayload['short_description'] === null || $productPayload['short_description'] === '') {
      $productPayload['short_description'] = $this->firstNonEmptyFromRow($firstRow, ["USP´s"]) ?? null;
    }

    // 4) Slug erzeugen (mit Hersteller-ID entdoppeln)
    $slug = \Illuminate\Support\Str::slug($name . '-' . (string) $this->manufacturerId);

    // 5) Finalen Payload ergänzen (Pflichtfelder)
    $finalProductPayload = array_merge($productPayload, [
      'product_name'    => $name,
      'slug'            => $slug,
      'product_type'    => 'variable',
      'manufacturer_id' => $this->manufacturerId,
      'status'          => 'draft',
    ]);

    // 6) Hauptprodukt upserten (Key: Hersteller + Produktname)
    /** @var \App\Models\Product $product */
    $product = \App\Models\Product::updateOrCreate(
      [
        'manufacturer_id' => $this->manufacturerId,
        'product_name'    => $name,
      ],
      // hier nur minimale Pflichtfelder, Rest sichern wir mit forceFill (Mass-Assignment umgehen)
      [
        'slug'         => $slug,
        'product_type' => 'variable',
        'status'       => 'draft',
      ]
    );

    // 7) Alle (auch evtl. nicht fillable) Felder sicher schreiben
    $product->forceFill($finalProductPayload)->save();

    Log::debug('Upserted product', [
      'id'               => $product->id,
      'manufacturer_id'  => $product->manufacturer_id,
      'product_name'     => $product->product_name,
      'slug'             => $product->slug,
      'product_number'   => $product->product_number ?? null,
      'ean'              => $product->ean ?? null,
      'variants_expected' => $rows->count(),
    ]);

    // 8) Varianten importieren
    foreach ($rows as $row) {
      $this->importVariation($product, $row);
    }
  }


  /**
   * Importiert oder aktualisiert eine einzelne Produktvariante.
   *
   * Nutzt die SKU (Referenz) aus der CSV-Zeile, um eine Variante zu identifizieren.
   * Führt ein `updateOrCreate` für die Variante durch und stößt die Zuweisung
   * der Attribute (z.B. Farbe, Größe) an.
   *
   * @param App\Models\Product  $product Das übergeordnete Hauptprodukt.
   * @param array               $row     Die CSV-Zeile, die die Daten der Variante enthält.
   * @return void
   */
  protected function importVariation(\App\Models\Product $product, array $row)
  {
    $referenceKey  = $this->mapping['reference'] ?? 'Reference';
    $referenceCols = is_array($referenceKey) ? $referenceKey : [$referenceKey];

    // Mapping-Fallback
    $ref = $this->firstNonEmptyFromRow($row, $referenceCols);

    // Spezifischer Fallback für Edelrid
    if ($ref === null || $ref === '') {
      $ref = $this->firstNonEmptyFromRow($row, ['Artikelnummer']);
    }

    if ($ref === null || $ref === '') {
      Log::warning('Variation skipped due to missing reference', [
        'group' => $product->product_name ?? '',
        'row_index' => $row['__row_index'] ?? null,
      ]);
      return;
    }

    $variationPayload       = [];
    $variationFieldsMapping = $this->mapping['variation_fields'] ?? [];

    foreach ($variationFieldsMapping as $dbField => $csvColumnOrList) {
      $cols = is_array($csvColumnOrList) ? $csvColumnOrList : [$csvColumnOrList];
      $val  = $this->firstNonEmptyFromRow($row, $cols);
      if ($val !== null) {
        $variationPayload[$dbField] = $val;
      }
    }

    $variation = \App\Models\ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $ref],
      $variationPayload
    );

    $this->handleVariationAttributes($variation, $row);
  }


  /**
   * Verknüpft Attributwerte mit einer Variante.
   *
   * Regeln:
   * - Primär wird das in $this->mapping['variation'] konfigurierte Mapping verwendet.
   * - Eine Auto‑Korrektur auf Edelrid‑Header wird NUR aktiviert,
   *   wenn die aktuelle CSV‑Zeile tatsächlich deutsche Edelrid‑Header enthält
   *   (z. B. "Farbe Bezeichnung", "Größen Bezeichnung", "Farb-Code", "Größen Code").
   *   → So bleibt Petzl (englische Header wie "Specifications", "Size") unberührt.
   *
   * @param \App\Models\ProductVariation $variation
   * @param array<string,mixed>          $row
   * @return void
   */
  protected function handleVariationAttributes(\App\Models\ProductVariation $variation, array $row): void
  {
    $variationMapping = $this->mapping['variation'] ?? [];

    // Kandidaten-Header für Edelrid erkennen (nach Normalisierung)
    $rowKeys = array_keys($row);
    $normalize = fn(string $s) => preg_replace('/\s+/u', ' ', trim(preg_replace('/[\x00-\x1F\x7F\xC2\xA0]/u', ' ', $s) ?? $s));
    $normalizedKeys = array_map($normalize, array_map('strval', $rowKeys));

    $edelridHeaderPresent = false;
    foreach (['Farbe Bezeichnung', 'Farb-Code', 'Farbcode', 'Farbe', 'Größen Bezeichnung', 'Größen Code', 'Größe'] as $probe) {
      if (in_array($normalize($probe), $normalizedKeys, true)) {
        $edelridHeaderPresent = true;
        break;
      }
    }

    // Nur wenn Edelrid-Header in der CSV vorhanden sind, ggf. auf deutsches Mapping umbiegen
    if ($edelridHeaderPresent) {
      // Falls Mapping leer/englisch ist, auf Edelrid-columns mappen
      $looksEnglish = isset($variationMapping['color']) || isset($variationMapping['size']);
      $usesSpecs    = in_array('Specifications', (array)($variationMapping['color'] ?? []), true)
        || in_array('Size', (array)($variationMapping['size'] ?? []), true);

      if (empty($variationMapping) || $looksEnglish || $usesSpecs) {
        $variationMapping = [
          'Farbe' => ['Farbe Bezeichnung', 'Farb-Code', 'Farbcode', 'Farbe'],
          'Größe' => ['Größen Bezeichnung', 'Größen Code', 'Größe'],
        ];
        // optional: einmalige Info pro Lauf – auskommentiert, um Logs ruhig zu halten
        // static $notice = false;
        // if (!$notice) { \Log::notice('Edelrid-Header erkannt – Variation-Mapping auto-korrigiert.'); $notice = true; }
      }
    }
    // Wenn KEINE Edelrid-Header vorhanden sind (Petzl), bleibt das bestehende Mapping unverändert!

    // Ab hier: striktes Anwenden des (ggf. angepassten) Mappings
    $attributeValueIds = [];

    foreach ($variationMapping as $attributeName => $csvColumnOrList) {
      $columns = is_array($csvColumnOrList) ? $csvColumnOrList : [$csvColumnOrList];

      // ersten nicht-leeren Wert aus den Kandidaten holen (mit deiner robusten Header-Normalisierung)
      $value = $this->firstNonEmptyFromRow($row, $columns);
      $value = $value !== null ? trim((string) $value) : '';

      if ($value === '') {
        continue;
      }

      $attribute = \App\Models\ProductAttribute::firstOrCreate(
        ['slug' => \Illuminate\Support\Str::slug($attributeName)],
        ['name' => $attributeName]
      );

      $attributeValue = \App\Models\ProductAttributeValue::firstOrCreate(
        ['attribute_id' => $attribute->id, 'slug' => \Illuminate\Support\Str::slug($value)],
        ['value' => $value]
      );

      $attributeValueIds[] = $attributeValue->id;
    }

    if (!empty($attributeValueIds)) {
      $variation->attributeValues()->sync($attributeValueIds);
    }
  }


  // ───────────────────────────────────────────────────────────────────────────────
  // HILFSFUNKTIONEN FÜR ROBUSTE HEADER-LOOKUPS
  // ───────────────────────────────────────────────────────────────────────────────

  /**
   * Normalisiert eine Header-Bezeichnung:
   * - trimmt (inkl. NBSP), ersetzt Steuerzeichen durch Space
   * - reduziert Mehrfach-Whitespace auf einen Space
   */
  protected function normalizeLabel(string $label): string
  {
    // NBSP & Steuerzeichen -> Space
    $s = preg_replace('/[\x00-\x1F\x7F\xC2\xA0]/u', ' ', $label) ?? $label;
    $s = trim($s);
    // Mehrfach-Whitespace auf 1 Space reduzieren
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return $s;
  }

  /**
   * Holt einen Zellwert aus $row anhand einer (evtl. unsauberen) Header-Bezeichnung.
   * Versucht: exakter Header → normalisierte Variante.
   *
   * @param array<string,mixed> $row
   */
  protected function valueByHeader(array $row, string $headerLabel): ?string
  {
    if (array_key_exists($headerLabel, $row)) {
      $v = $row[$headerLabel];
      return is_scalar($v) ? (string) $v : (is_null($v) ? null : (string) $v);
    }

    $normalizedKey = $this->normalizeLabel($headerLabel);

    if (empty($this->__normalizedHeaderKeyMap)) {
      foreach ($row as $k => $_) {
        $this->__normalizedHeaderKeyMap[$this->normalizeLabel((string) $k)] = $k;
      }
    }

    if (isset($this->__normalizedHeaderKeyMap[$normalizedKey])) {
      $realKey = $this->__normalizedHeaderKeyMap[$normalizedKey];
      $v = $row[$realKey];
      return is_scalar($v) ? (string) $v : (is_null($v) ? null : (string) $v);
    }

    return null;
  }

  /**
   * Liefert den ersten nicht-leeren Wert aus einer Liste möglicher CSV-Spalten.
   *
   * @param array<string,mixed> $row
   * @param array<int,string>   $columns
   */
  protected function firstNonEmptyFromRow(array $row, array $columns): ?string
  {
    foreach ($columns as $col) {
      $val = $this->valueByHeader($row, $col);
      if ($val !== null && trim($val) !== '') {
        return trim($val);
      }
    }
    return null;
  }

  /**
   * Ermittelt den Gruppierungs-Schlüssel robust aus einer Zeile.
   * Unterstützt 'group_by' als String **oder** als Array (Fallback-Reihenfolge).
   */
  protected function getGroupKeyFromRow(array $row): string
  {
    $groupBy = $this->mapping['group_by'] ?? '';
    $columns = is_array($groupBy) ? $groupBy : (($groupBy !== '') ? [$groupBy] : []);

    if (empty($columns)) {
      return '';
    }

    $val = $this->firstNonEmptyFromRow($row, $columns);
    return (string) ($val ?? '');
  }

  /**
   * Gruppiert robust:
   * 1) nach group_by,
   * 2) wenn leer → nach reference,
   * 3) wenn immer noch leer → nach Zeilenindex (damit nichts verloren geht).
   *
   * @param \Illuminate\Support\Collection<int,array<string,mixed>> $rows
   * @return \Illuminate\Support\Collection<string,\Illuminate\Support\Collection>
   */
  protected function groupRowsByMapping(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
  {
    $referenceKey  = $this->mapping['reference'] ?? 'Reference';
    $referenceCols = is_array($referenceKey) ? $referenceKey : [$referenceKey];

    $indexed = $rows->values()->map(function (array $row, int $i) {
      $row['__row_index'] = $i;
      return $row;
    });

    return $indexed->groupBy(function (array $row): string {
      // 1) Primär: group_by (Mapping, inkl. Array-Fallbacks)
      $groupKey = $this->getGroupKeyFromRow($row);
      if ($groupKey !== '') {
        return $groupKey;
      }

      // 2) Sekundär: reference aus Mapping (z. B. Artikelnummer)
      $referenceKey  = $this->mapping['reference'] ?? 'Reference';
      $referenceCols = is_array($referenceKey) ? $referenceKey : [$referenceKey];
      $ref = $this->firstNonEmptyFromRow($row, $referenceCols);
      if ($ref !== null && $ref !== '') {
        return $ref;
      }

      // 3) Spezieller Fallback für Edelrid: explizit auf die realen Header gehen
      $explicitName = $this->firstNonEmptyFromRow($row, ['Artikelbezeichnung']);
      if ($explicitName !== null && $explicitName !== '') {
        return $explicitName;
      }
      $explicitRef = $this->firstNonEmptyFromRow($row, ['Artikelnummer']);
      if ($explicitRef !== null && $explicitRef !== '') {
        return $explicitRef;
      }

      // 4) Letzter Notnagel (sollte jetzt praktisch nicht mehr vorkommen)
      return '__ROW__:' . ($row['__row_index'] ?? 'x');
    });
  }
}
