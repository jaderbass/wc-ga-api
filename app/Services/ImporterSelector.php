<?php

namespace App\Services;

use App\Imports\Manufacturer\ImporterForKratos;
use App\Imports\Manufacturer\ImporterForAliens;

class ImporterSelector
{
  public static function forManufacturer(int $id)
  {
    return match ($id) {
      1 => new ImporterForKratos(),
      2 => new ImporterForAliens(),
      default => throw new \Exception("Kein Importer für Hersteller-ID $id gefunden."),
    };
  }
}
