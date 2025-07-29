<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseCsvImporter; // oder BaseXmlImporter, je nach Format
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ImporterForPetzl extends BaseCsvImporter
{
  protected function model(): string
  {
    return Product::class;
  }

  protected function fixedValues(): array
  {
    return [
      'manufacturer_id' => 3, // Petzl-ID
    ];
  }

  protected function columnMap(): array
  {
    return [
      'productnumber' => 'SKU',
      'productname' => 'PRODUCT_NAME',
      'description' => 'DESCRIPTION',
      'eancode' => 'EAN',
      'weight' => 'WEIGHT',
    ];
  }

  protected function upsertRecord(array $data): void
  {
    $model = $this->model();

    if (empty($data['productnumber'])) {
      Log::warning('❗ Kein productnumber gesetzt – Datensatz wird ignoriert', $data);
      return;
    }

    $record = $model::where('productnumber', $data['productnumber'])->first();

    if ($record) {
      $record->update($data);
      Log::info("Produkt aktualisiert", ['id' => $record->id]);
    } else {
      $model::create($data);
      Log::info("Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
    }
  }
}
