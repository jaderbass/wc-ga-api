<?php

namespace App\Services;

use App\Models\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Importers\Contracts\CsvImporterContract;
use App\Importers\Contracts\HandlesUploadedFile;

class ImporterSelector
{
  /**
   * Wählt automatisch den richtigen Importer anhand Hersteller & Quelle.
   *
   * @throws InvalidArgumentException
   */ /**
   * @return CsvImporterContract|HandlesUploadedFile
   */
  public static function forManufacturer(int|string $manufacturerId): CsvImporterContract|HandlesUploadedFile
  {
    $m = Manufacturer::query()->findOrFail($manufacturerId);
    $short = Str::before(Str::slug((string)($m->slug ?: $m->manufacturer ?: '')), '-');

    Log::debug('ImporterSelector resolving', [
      'manufacturer_id'   => $m->id,
      'manufacturer_name' => $m->manufacturer ?? null,
      'manufacturer_slug' => $m->slug ?? null,
      'normalized_key'    => $short,
    ]);

    switch ($short) {
      case 'edelrid':
        // Dein Edelrid-Konstruktor erwartet 1 Argument (ID)
        $instance = new \App\Imports\Manufacturer\ImporterForEdelrid((int) $m->id);
        break;

      case 'petzl':
        $instance = new \App\Imports\Manufacturer\ImporterForPetzl((int) $m->id);
        break;

      default:
        $instance = new \App\Importers\GenericCsvProductImporter(
          mappingFile: $short ?: 'petzl',
          manufacturerId: (int) $m->id
        );
        break;
    }

    Log::info('Importer ausgewählt', ['manufacturer' => $m->manufacturer, 'class' => get_class($instance)]);
    return $instance;
  }

  /**
   * Quelle (Datei oder URL) behandeln.
   */
  public static function handleImport(object $importer, string $type, mixed $source): void
  {
    Log::debug('ImporterSelector.handleImport ENTER', [
      'type'     => $type,
      'importer' => get_class($importer),
    ]);

    if ($type === 'csv') {
      // bevorzugt: Pfad-basiert
      if (is_string($source) && method_exists($importer, 'import')) {
        $importer->import($source);
        return;
      }
      // Fallback: Upload-Objekt
      if ($source instanceof TemporaryUploadedFile && method_exists($importer, 'handleUploadedFile')) {
        $importer->handleUploadedFile($source);
        return;
      }
      throw new \RuntimeException('CSV: Kein passender Entry-Point im Importer gefunden.');
    }

    if ($type === 'xml' && method_exists($importer, 'importFromXml')) {
      $importer->importFromXml($source);
      return;
    }

    if ($type === 'api' && method_exists($importer, 'importFromApi')) {
      $importer->importFromApi($source);
      return;
    }

    throw new \InvalidArgumentException("Unbekannter Import-Typ oder fehlende Methode: {$type}");
  }
}
