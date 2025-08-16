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
   * GenericCsvProductImporter constructor.
   *
   * @param string $mappingFile Der Mapping-Schlüssel (z. B. "petzl" für config/import_mappings/petzl.php)
   */
  public function __construct(
    protected string $mappingFile,
    protected ?int $manufacturerId = null // 👈 neu
  ) {
    $this->mapping = config("import_mappings.$mappingFile");
  }

  /**
   * Führt den CSV-Import aus.
   *
   * @param string $filePath Pfad zur CSV-Datei mit Produktdaten.
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
   * Importiert ein Hauptprodukt und alle zugehörigen Varianten.
   *
   * @param string $groupKey Schlüssel zum Gruppieren (z. B. Produktname)
   * @param \Illuminate\Support\Collection|array $rows Die Zeilen, die zu einem Produkt gehören
   * @return void
   */
  protected function importProductGroup(string $groupKey, $rows)
  {
    Log::debug('Import group', ['groupKey' => $groupKey, 'rows' => count($rows)]);

    $firstRow = $rows->first();
    $referenceKey = $this->mapping['reference'] ?? 'Reference';

    // Name robuster bestimmen (Petzl hat oft leere Felder in manchen Zeilen)
    $nameKey = $this->mapping['product']['product_name'] ?? null;
    $descKey = $this->mapping['product']['description'] ?? null;

    $firstNonEmpty = collect($rows)->first(fn($r) => !empty(trim($r[$nameKey] ?? ''))); // optional!!
    $name = trim($firstNonEmpty[$nameKey] ?? '') ?: trim($groupKey) ?: 'Unnamed Product';
    // $name = trim($firstRow[$nameKey] ?? '') ?: trim($groupKey) ?: 'Unnamed Product';
    $description = trim($firstRow[$descKey] ?? '') ?: null;

    // deterministischer Slug (ohne -1/-2-Anhängsel)
    $slug = Str::slug($name) ?: Str::slug('product');

    // 1) Match: slug + (optional) manufacturer_id
    $query = \App\Models\Product::query()->where('slug', $slug);
    if ($this->manufacturerId) {
      $query->where('manufacturer_id', $this->manufacturerId);
    }
    $product = $query->first();

    $payload = [
      'product_name'   => $name,
      'description'    => $description,
      'product_type'   => 'variable',
      'manufacturer_id' => $this->manufacturerId,
    ];

    // 2) Upsert
    if ($product) {
      $product->fill($payload)->save();
    } else {
      $product = \App\Models\Product::create(array_merge($payload, [
        'slug' => $slug, // nur beim erstmaligen Anlegen
        // optional: productnumber / mpn etc. aus CSV, wenn gewünscht
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
   * Importiert eine einzelne Produktvariante.
   *
   * @param Product $product Das zugehörige Hauptprodukt
   * @param array $row Die CSV-Zeile mit Variantendaten
   * @return void
   */
  protected function importVariation(Product $product, array $row)
  {
    $referenceKey = $this->mapping['reference'] ?? 'Reference';
    $ref = isset($row[$referenceKey]) ? trim($row[$referenceKey]) : null;
    if ($ref === null || $ref === '') {
      return; // ohne SKU keine Variante
    }

    // 1. Variante erstellen oder aktualisieren (ohne die alte 'attributes' Spalte)
    $variation = ProductVariation::updateOrCreate(
      ['product_id' => $product->id, 'sku' => $ref], // 👈 Upsert-Key
      [
        'regular_price'  => $row['Regular price'] ?? null,
        'sale_price'     => $row['Sale price'] ?? null,
        'stock_quantity' => $row['Stock quantity'] ?? null,
      ]
    );

    // 2. Attribute über die neuen Tabellen zuweisen
    $this->handleVariationAttributes($variation, $row);
  }

  /**
   * Verarbeitet die Attribute für eine gegebene Variation.
   *
   * @param ProductVariation $variation Die Produktvariante.
   * @param array $row Die CSV-Zeile.
   * @return void
   */
  protected function handleVariationAttributes(ProductVariation $variation, array $row): void
  {
    $attributeValueIds = [];
    $variationMapping = $this->mapping['variation'] ?? [];

    foreach ($variationMapping as $attributeName => $csvColumn) {
      $value = trim($row[$csvColumn] ?? '');

      if (empty($value)) {
        continue;
      }

      // z.B. aus 'color' wird 'Farbe' und 'farbe'
      $attributeDisplayName = ucfirst($attributeName);
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

    // Verknüpft die Attributwerte mit der Variante über die Pivot-Tabelle
    if (!empty($attributeValueIds)) {
      $variation->attributeValues()->sync($attributeValueIds);
    }
  }
}
