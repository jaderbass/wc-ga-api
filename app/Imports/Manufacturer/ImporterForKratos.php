<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImporterForKratos
{
  public function handleUploadedFile($file): void
  {
    $path = $file->store('imports');
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setHeaderOffset(0);

    foreach ($csv->getRecords() as $row) {
      Product::create([
        'productname' => $row['Artikelname'],
        'skucode' => $row['SKU'],
        'manufacturer_id' => 1,
        // weitere Felder ...
      ]);
    }
  }
}
