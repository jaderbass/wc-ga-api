<?php

namespace App\Services;

use App\Imports\Manufacturer\ImporterForKratos;
use App\Imports\Manufacturer\ImporterForAliens;
use App\Imports\Manufacturer\ImporterForKask;
use App\Imports\Manufacturer\ImporterForPetzl;
use App\Imports\Manufacturer\ImporterForSingingRock;

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
    return match ($id) {
      1 => new ImporterForAliens(),
      2 => new ImporterForKask(),
      3 => new ImporterForPetzl(),
      4 => new ImporterForKratos(),
      5 => new ImporterForSingingRock(),
      default => throw new \Exception("Kein Importer für Hersteller-ID $id gefunden."),
    };
  }
}
