<?php

namespace App\Services;

use App\Models\Manufacturer;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ImporterSelector
{
  /**
   * Wählt automatisch den richtigen Importer anhand Hersteller & Quelle.
   *
   * @throws InvalidArgumentException
   */
  public static function forManufacturer(int $manufacturerId): object
  {
    $manufacturer = Manufacturer::find($manufacturerId);

    if (! $manufacturer) {
      throw new InvalidArgumentException("Hersteller mit ID {$manufacturerId} nicht gefunden.");
    }

    $importerClass = match ($manufacturer->manufacturer) {
      'Aliens'        => \App\Imports\Manufacturer\ImporterForAliens::class,
      'Kask'          => \App\Imports\Manufacturer\ImporterForKask::class,
      'Petzl'         => \App\Imports\Manufacturer\ImporterForPetzl::class,
      'Singing Rock'  => \App\Imports\Manufacturer\ImporterForSingingRock::class,
      'Kratos Safety' => \App\Imports\Manufacturer\ImporterForKratos::class,
      default => null,
    };

    if (! $importerClass || ! class_exists($importerClass)) {
      throw new InvalidArgumentException("Kein Importer für Hersteller {$manufacturer->manufacturer} gefunden.");
    }

    Log::info("Importer ausgewählt", [
      'manufacturer' => $manufacturer->manufacturer,
      'class' => $importerClass,
    ]);

    return new $importerClass();
  }

  /**
   * Quelle (Datei oder URL) behandeln.
   */
  public static function handleImport(object $importer, string $sourceType, mixed $source): void
  {
    match ($sourceType) {
      'csv' => $importer->handleUploadedFile($source),
      'xml' => $importer->handleUploadedXmlFile($source),
      'api' => $importer->handleFromUrl($source),
      default => throw new InvalidArgumentException("Ungültiger Import-Typ: {$sourceType}"),
    };
  }
}
