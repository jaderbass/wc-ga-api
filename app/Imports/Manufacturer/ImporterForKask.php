<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseCsvImporter;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ImporterForKask extends BaseCsvImporter
{
  protected function model(): string
  {
    return Product::class;
  }

  protected function fixedValues(): array
  {
    return [
      'manufacturer_id' => 2, // Kask-ID
    ];
  }

  protected function columnMap(): array
  {
    return [
      'productnumber' => 'PART #',
      'productname' => 'DESCRIPTION',
      'eancode' => 'EAN CODE',
      'width' => 'SWIDHT',
      'length' => 'SLENGHT',
      'height' => 'SHEIGHT',
      'pcsperbox' => 'PCS X BOX',
      'boxwidth' => 'MWIDHT',
      'boxlength' => 'MLENGHT',
      'boxheight' => 'MHEIGHT',
      'weight' => 'GROSS WEIGHT',
      'manufacturercountry' => 'COUNTRY OF ORIGIN',
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

    // Vor dem Speichern in der Importer-Klasse
    Log::info('Import Row Data:', $data);


    if ($record) {
      $record->update($data);
      Log::info("Produkt aktualisiert", ['id' => $record->id]);
    } else {
      $model::create($data);
      Log::info("Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
    }
  }
}
