<?php

/**
 * class ImporterForKratos
 *
 * Creates the import for the manufacturer "Kratos Safety"
 *
 * @author Jörg Aderhold <joerg@jaderbass.de> https://jaderbass.de
 *
 * @since 1.0.0
 *
 * @package App\Imports\Manufacturer
 */

namespace App\Imports\Manufacturer;

use App\Models\Product;
use League\Csv\Reader;
use App\Helpers\CsvValueSanitizer as San;

class ImporterForKratos
{
  public function handleUploadedFile(string $path): void
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';'); // 👈 ganz wichtig!
    $csv->setHeaderOffset(0);

    foreach ($csv->getRecords() as $row) {
      Product::create([
        'manufacturer_id' => 4,
        'productname' => San::toNullableString($row['Artikelname']),
        'skucode' => San::toNullableString($row['SKU']),
        // weitere Felder ...
      ]);
    }
  }
}
