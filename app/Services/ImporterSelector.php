<?php

namespace App\Services;

use App\Imports\Manufacturer\ImporterForKratos;
use App\Imports\Manufacturer\ImporterForAliens;
use App\Imports\Manufacturer\ImporterForKask;
use App\Imports\Manufacturer\ImporterForPetzl;

class ImporterSelector
{
  public static function forManufacturer(int $id)
  {
    return match ($id) {
      1 => new ImporterForAliens(),
      2 => new ImporterForKask(),
      3 => new ImporterForPetzl(),
      4 => new ImporterForKratos(),
      default => throw new \Exception("Kein Importer für Hersteller-ID $id gefunden."),
    };
  }
}
