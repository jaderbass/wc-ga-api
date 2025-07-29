<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseCsvImporter;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ImporterForAliens extends BaseCsvImporter
{
  protected function model(): string
  {
    return Product::class;
  }

  protected function fixedValues(): array
  {
    return [
      'manufacturer_id' => 1, // Aliens-ID
    ];
  }

  protected function columnMap(): array
  {
    return [
      'productnumber' => 'PRODUCT_NO',
      'productname' => 'NAME',
      'eancode' => 'EAN',
      'description' => 'DESCRIPTION',
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
