<?php

/**
 * class ImporterForKask
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

class ImporterForKask
{
  public function handleUploadedFile(string $path): void
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';'); // 👈 ganz wichtig!
    $csv->setHeaderOffset(0);

    foreach ($csv->getRecords() as $row) {
      Product::create([
        'manufacturer_id' => 2,
        'productnumber' => San::toNullableString($row['PART #']),
        'productname' => San::toNullableString($row['DESCRIPTION']),
        'eancode' => San::toNullableString($row['EAN CODE']),
        'width' => San::toNullableInt($row['SWIDHT']),
        'length' => San::toNullableInt($row['SLENGHT']),
        'height' => San::toNullableInt($row['SHEIGHT']),
        'manufacturercountry' => San::toNullableString($row['COUNTRY OF ORIGIN']),
        'pcsperbox' => San::toNullableInt($row['PCS X BOX']),
        'boxwidth' => San::toNullableInt($row['MWIDHT']),
        'boxlength' => San::toNullableInt($row['MLENGHT']),
        'boxheight' => San::toNullableInt($row['MHEIGHT']),
        'weight' => San::toNullableInt($row['GROSS WEIGHT']),
        'skucode' => San::toNullableString($row['PART #']),
      ]);
    }
  }
}
