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
      1 => new ImporterForKratos(),
      2 => new ImporterForAliens(),
      3 => new ImporterForKask(),
      4 => new ImporterForPetzl(),
      default => throw new \Exception("Kein Importer für Hersteller-ID $id gefunden."),
    };
  }
}
