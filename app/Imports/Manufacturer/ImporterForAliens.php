<?php

/**
 * class ImporterForAliens
 *
 * Creates the import for the manufacturer "Aliens"
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

class ImporterForAliens
{
  public function handleUploadedFile(string $path): void
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';'); // 👈 ganz wichtig!
    $csv->setHeaderOffset(0);
    
    foreach ($csv->getRecords() as $row) {
      Product::create([
        'manufacturer_id' => 1,
        'productname' => San::toNullableString($row['Artikelbezeichnung']),
        'productnumber' => San::toNullableString($row['Artikelnummer']),
        'eancode' => San::toNullableString($row['EAN']),
        'price' => San::toNullableInt($row['eVK']),
        // weitere Felder ...
      ]);
    }
  }
}
