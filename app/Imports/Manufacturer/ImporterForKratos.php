<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseCsvImporter;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ImporterForKratos extends BaseCsvImporter
{
  protected function model(): string
  {
    return Product::class;
  }

  protected function fixedValues(): array
  {
    return [
      'manufacturer_id' => 4, // Kratos-ID
    ];
  }

  protected function columnMap(): array
  {
    return [
      'productnumber'       => 'PRODUCT_NO',
      'productname'         => 'NAME',
      'description'         => 'DESCRIPTION',
      'shortdescription'    => 'SHORT_DESCRIPTION',
      'eancode'             => 'EAN',
      'skucode'             => 'SKU',
      'price'               => 'PRICE',
      'regularprice'        => 'REGULAR_PRICE',
      'saleprice'           => 'SALE_PRICE',
      'width'               => 'WIDTH',
      'length'              => 'LENGTH',
      'height'              => 'HEIGHT',
      'weight'              => 'WEIGHT',
      'unit'                => 'UNIT',
      'unitprice'           => 'UNIT_PRICE',
      'pcsperbox'           => 'PCS_PER_BOX',
      'boxwidth'            => 'BOX_WIDTH',
      'boxlength'           => 'BOX_LENGTH',
      'boxheight'           => 'BOX_HEIGHT',
      'manufacturercountry' => 'COUNTRY',
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
