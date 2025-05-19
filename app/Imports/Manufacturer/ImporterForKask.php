<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseCsvImporter;
use App\Models\Product;

class ImporterForKask extends BaseCsvImporter
{
  protected function model(): string
  {
    return Product::class;
  }

  protected function columnMap(): array
  {
    return [
      'productnumber'       => 'PART #',
      'productname'         => 'DESCRIPTION',
      'eancode'             => 'EAN CODE',
      'width'               => 'SWIDHT',
      'length'              => 'SLENGHT',
      'height'              => 'SHEIGHT',
      'pcsperbox'           => 'PCS X BOX',
      'boxwidth'            => 'MWIDHT',
      'boxlength'           => 'MLENGHT',
      'boxheight'           => 'MHEIGHT',
      'weight'              => 'GROSS WEIGHT',
      'manufacturercountry' => 'COUNTRY OF ORIGIN',
    ];
  }

  protected function fixedValues(): array
  {
    return [
      'manufacturer_id' => 2, // ID von KASK
    ];
  }
}
