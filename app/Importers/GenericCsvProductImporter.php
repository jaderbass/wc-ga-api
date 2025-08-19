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
   * Liest die CSV-Datei, normalisiert die Daten (z.B. trimmt Leerzeichen),
   * gruppiert die Zeilen zu Hauptprodukten und startet den Import für jede Gruppe
   * innerhalb einer Datenbanktransaktion.
   *
   * @param string $filePath Der Pfad zur hochgeladenen CSV-Datei.
   * @return void
   */
  public function import(string $filePath): void
  {
    $csv = Reader::createFromPath($filePath, 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);
    $csv->skipEmptyRecords();

    // records + Header holen
    $records = iterator_to_array($csv->getRecords());
    $headers = $csv->getHeader();

    Log::debug('CSV Header', ['headers' => $headers, 'count' => count($records)]);

    // Trim alle Zellen, damit „Product name “ ≠ „Product name“ verhindert wird
    $normalized = collect($records)->map(function (array $row) {
      foreach ($row as $k => $v) {
        $row[$k] = is_string($v) ? trim($v) : $v;
      }
      return $row;
    });

    // Gruppieren nach (getrimmtem) group_by
    $groupByKey = $this->mapping['group_by'];
    $grouped = $normalized->groupBy(fn($r) => trim($r[$groupByKey] ?? ''));

    DB::transaction(function () use ($grouped) {
      foreach ($grouped as $groupKey => $rows) {
        $this->importProductGroup($groupKey, $rows);
      }
    });
  }

  /**
   * Importiert eine Produktgruppe, bestehend aus einem Hauptprodukt und dessen Varianten.
   *
   * Ermittelt, ob das Hauptprodukt bereits existiert (via Slug und optional Hersteller-ID).
   * Legt das Produkt an oder aktualisiert es (Upsert-Logik).
   * Anschließend werden die einzelnen Zeilen als Produktvarianten importiert.
   *
   * @param string $groupKey Der Wert, nach dem die Produkte gruppiert wurden (z.B. Produktname oder Artikelnummer).
   * @param \Illuminate\Support\Collection $rows Eine Sammlung von CSV-Zeilen, die zu dieser Produktgruppe gehören.
   * @return void
   */
  protected function importProductGroup(string $groupKey, \Illuminate\Support\Collection $rows)
  {
    Log::debug('Import group', ['groupKey' => $groupKey, 'rows' => count($rows)]);

    $referenceKey = $this->mapping['reference'] ?? 'Reference';

    // --- Robuste Payload-Erstellung für das Hauptprodukt ---
    // Wir durchlaufen das 'product'-Mapping und suchen für jedes Feld
    // den ersten nicht-leeren Wert innerhalb der gesamten Produktgruppe.
    $productMapping = $this->mapping['product'] ?? [];
    $productPayload = [];

    foreach ($productMapping as $dbField => $csvColumnOrList) {
      $columns   = is_array($csvColumnOrList) ? $csvColumnOrList : [$csvColumnOrList];

      // Finde die erste Zeile in der Gruppe, die für mind. eine der Spalten einen Wert hat.
      $sourceRow = $rows->first(function (array $row) use ($columns) {
        return $this->firstNonEmptyFromRow($row, $columns) !== null;
      });

      if ($sourceRow) {
        $val = $this->firstNonEmptyFromRow($sourceRow, $columns);
        if ($val !== null) {
          $productPayload[$dbField] = $val;
        }
      }
    }

    // Name/Slug wie gehabt (dein bestehender Code hier weiterführen)
    $name = trim((string) ($productPayload['product_name'] ?? $productPayload['name'] ?? $groupKey));
    $slug = Str::slug($name);

    $finalProductPayload = array_merge($productPayload, [
      'product_name'    => $name,
      'product_type'    => 'variable',
      'manufacturer_id' => $this->manufacturerId,
      'status'          => 'draft',
    ]);

    // 1) Match: slug + (optional) manufacturer_id
    $query = \App\Models\Product::query()->where('slug', $slug);
    if ($this->manufacturerId) {
      $query->where('manufacturer_id', $this->manufacturerId);
    }
    $product = $query->first();

    // 2) Upsert
    if ($product) {
      $product->fill($finalProductPayload)->save();
    } else {
      $product = \App\Models\Product::create(array_merge($finalProductPayload, [
        'slug' => $slug, // nur beim erstmaligen Anlegen
      ]));
    }

    $skipped = 0;
    foreach ($rows as $row) {
      if (empty($row[$referenceKey])) {
        $skipped++;
        continue;
      }
      $this->importVariation($product, $row);
    }

    if ($skipped > 0) {
      Log::warning('Variations skipped due to missing reference', [
        'group' => $groupKey,
        'skipped' => $skipped,
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
   * @param App\Models\Product  $product Das übergeordnete Hauptprodukt.
   * @param array               $row     Die CSV-Zeile, die die Daten der Variante enthält.
   * @return void
   */
  protected function importVariation(Product $product, array $row)
  {
    $referenceKey = $this->mapping['reference'] ?? 'Reference';
    $ref = isset($row[$referenceKey]) ? trim((string) $row[$referenceKey]) : null;
    if ($ref === null || $ref === '') {
      return; // ohne eindeutige Referenz (SKU) keine Variante
    }

    // 1) Payload für die Variante mit Fallback‑Spalten bauen
    $variationPayload       = [];
    $variationFieldsMapping = $this->mapping['variation_fields'] ?? [];

    foreach ($variationFieldsMapping as $dbField => $csvColumnOrList) {
      $columns = is_array($csvColumnOrList) ? $csvColumnOrList : [$csvColumnOrList];
      $val     = $this->firstNonEmptyFromRow($row, $columns);
      if ($val !== null) {
        $variationPayload[$dbField] = $val;
      }
    }

    // 2) Upsert der Variante
    $variation = ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $ref],
      $variationPayload
    );

    // 3) Attribute zuweisen (Farbe/Größe etc. mit Fallbacks)
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
   * @param \App\Models\ProductVariation  $variation Die zu bearbeitende Produktvariante.
   * @param array                         $row       Die CSV-Zeile mit den Attributwerten.
   * @return void
   */
  protected function handleVariationAttributes(ProductVariation $variation, array $row): void
  {
    $attributeValueIds  = [];
    $variationMapping   = $this->mapping['variation'] ?? [];

    foreach ($variationMapping as $attributeName => $csvColumnOrList) {
      $columns = is_array($csvColumnOrList) ? $csvColumnOrList : [$csvColumnOrList];

      $value = $this->firstNonEmptyFromRow($row, $columns);
      $value = $value !== null ? trim((string) $value) : '';

      if ($value === '') {
        continue;
      }

      // Attribut + Wert erstellen/ermitteln
      $attribute = ProductAttribute::firstOrCreate(
        ['slug' => Str::slug($attributeName)],
        ['name' => $attributeName]
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
   * Liefert den ersten nicht‑leeren Wert aus einer Liste von CSV‑Spalten.
   *
   * @param array        $row      CSV‑Zeile (assoziatives Array Kopf→Wert)
   * @param array<int,string> $columns  Liste möglicher Spaltenüberschriften (Priorität von links nach rechts)
   * @return string|null
   */
  protected function firstNonEmptyFromRow(array $row, array $columns): ?string
  {
    foreach ($columns as $col) {
      if (!array_key_exists($col, $row)) {
        continue;
      }
      $val = trim((string) $row[$col]);
      if ($val !== '') {
        return $val;
      }
    }
    return null;
  }
}
