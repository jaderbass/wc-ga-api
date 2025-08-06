<?php

namespace App\Importers;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

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
  public function __construct(protected string $mappingFile)
  {
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
    $csv->setHeaderOffset(0);
    $records = iterator_to_array($csv->getRecords());

    // Gruppieren nach Hauptprodukt
    $grouped = collect($records)->groupBy($this->mapping['group_by']);

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
    $firstRow = $rows->first();

    // Produkt anlegen oder updaten
    $product = Product::updateOrCreate(
      ['sku' => $firstRow[$this->mapping['reference']] ?? null],
      [
        'name' => $firstRow[$this->mapping['product']['name']] ?? 'Unnamed Product',
        'description' => $firstRow[$this->mapping['product']['description']] ?? null,
        'product_type' => 'variable'
      ]
    );

    // Varianten anlegen/updaten
    foreach ($rows as $row) {
      $this->importVariation($product, $row);
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

    if (empty($row[$referenceKey])) {
      return;
    }

    ProductVariation::updateOrCreate(
      ['sku' => $row[$referenceKey]],
      [
        'product_id' => $product->id,
        'regular_price' => $row['Regular price'] ?? null,
        'sale_price' => $row['Sale price'] ?? null,
        'stock_quantity' => $row['Stock quantity'] ?? null,
        'attributes' => json_encode([
          'color' => $row[$this->mapping['variation']['color']] ?? null,
          'size'  => $row[$this->mapping['variation']['size']] ?? null,
        ])
      ]
    );
  }
}
