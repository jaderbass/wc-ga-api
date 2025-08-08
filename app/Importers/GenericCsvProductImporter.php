<?php

namespace App\Importers;

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
    $nameKey = $this->mapping['product']['name'] ?? null;
    $descKey = $this->mapping['product']['description'] ?? null;

    $name = trim($firstRow[$nameKey] ?? '') ?: trim($groupKey) ?: 'Unnamed Product';
    $description = trim($firstRow[$descKey] ?? '') ?: null;

    $slugBase = \Illuminate\Support\Str::slug($name) ?: 'product';
    $slug = $slugBase;
    $i = 1;
    while (Product::where('slug', $slug)->exists()) {
      $slug = "{$slugBase}-{$i}";
      $i++;
    }

    $product = Product::updateOrCreate(
      // Lieber über slug matchen (Parent-SKU ist oft leer)
      ['slug' => $slug],
      [
        'name'         => $name,
        'description'  => $description,
        'product_type' => 'variable',
      ]
    );

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
      return;
    }

    ProductVariation::updateOrCreate(
      ['sku' => $ref],
      [
        'product_id'      => $product->id,
        'regular_price'   => $row['Regular price'] ?? null,
        'sale_price'      => $row['Sale price'] ?? null,
        'stock_quantity'  => $row['Stock quantity'] ?? null,
        'attributes'      => json_encode([
          'color' => isset($this->mapping['variation']['color']) ? ($row[$this->mapping['variation']['color']] ?? null) : null,
          'size'  => isset($this->mapping['variation']['size'])  ? ($row[$this->mapping['variation']['size']]  ?? null) : null,
        ]),
      ]
    );
  }
}
