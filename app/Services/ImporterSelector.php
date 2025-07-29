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
   * @param int $manufacturerId
   * @return \App\Imports\BaseXmlImporter
   * @throws \Exception
   */
  public static function forManufacturer(int $manufacturerId): array
  {
    $manufacturer = Manufacturer::findOrFail($manufacturerId);

    return [
      'importer' => match ($manufacturer->id) {
        1 => new ImporterForAliens(),         // Aliens
        2 => new ImporterForKask(),           // Kask
        3 => new ImporterForPetzl(),          // Petzl
        4 => new ImporterForKratos(),         // Kratos
        5 => new ImporterForSingingRock(),    // Singing Rock
        default => throw new \Exception('Kein Importer für diesen Hersteller implementiert'),
      },
      'type' => $manufacturer->import_type,     // csv, xml oder api
      'api_url' => $manufacturer->api_url,
      'api_user' => $manufacturer->api_user,
      'api_password' => $manufacturer->api_password,
    ];
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
