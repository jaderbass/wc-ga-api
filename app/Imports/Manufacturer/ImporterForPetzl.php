<?php

/**
 * class ImporterForPetzl
 *
 * Creates the import for the manufacturer "Petzl"
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

class ImporterForPetzl
{
  public function handleUploadedFile(string $path): void
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';'); // 👈 ganz wichtig!
    $csv->setHeaderOffset(0);

    foreach ($csv->getRecords() as $row) {
      Product::create([
        'manufacturer_id' => 4,
        'productname' => San::toNullableString($row['Product Name']),
        'price' => San::toNullableInt($row['Unit Price VAT excl.']),
        'description' => San::toNullableString($row['Description']),
        // weitere Felder ...
      ]);
    }
  }
}
