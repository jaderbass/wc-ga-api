<?php

namespace App\Services;

use App\Models\Manufacturer;
use App\Imports\Manufacturer\ImporterForKratos;
use App\Imports\Manufacturer\ImporterForAliens;
use App\Imports\Manufacturer\ImporterForKask;
use App\Imports\Manufacturer\ImporterForPetzl;
use App\Imports\Manufacturer\ImporterForSingingRock;
use InvalidArgumentException;

class ImporterSelector
{
  /**
   * Wählt den passenden Importer basierend auf der Hersteller-ID.
   *
   * @param int $id
   * @return \App\Imports\BaseXmlImporter
   * @throws \Exception
   */
  public static function forManufacturer(int $id)
  {
    $manufacturer = Manufacturer::findOrFail($id);

    return match ($manufacturer->import_type) {
      'csv' => self::csvImporter($manufacturer),
      'xml' => self::xmlImporter($manufacturer),
      'api' => self::apiImporter($manufacturer),
      default => throw new InvalidArgumentException("Unbekannter Import-Typ: {$manufacturer->import_type}"),
    };
  }

  protected static function csvImporter(Manufacturer $manufacturer)
  {
    return match ($manufacturer->manufacturer) {
      'Aliens' => new ImporterForAliens(),
      'Kask' => new ImporterForKask(),
      'Kratos' => new ImporterForKratos(),
      'Petzl' => new ImporterForPetzl(),
      default => throw new InvalidArgumentException("Kein CSV-Importer für {$manufacturer->manufacturer} definiert"),
    };
  }

  protected static function xmlImporter(Manufacturer $manufacturer)
  {
    return match ($manufacturer->manufacturer) {
      'SingingRock' => new ImporterForSingingRock(),
      default => throw new InvalidArgumentException("Kein XML-Importer für {$manufacturer->manufacturer} definiert"),
    };
  }

  protected static function apiImporter(Manufacturer $manufacturer)
  {
    // später: API-Importer implementieren
    throw new InvalidArgumentException("API-Importer noch nicht implementiert für {$manufacturer->manufacturer}");
  }
}
