<?php

namespace App\Services;

use App\Models\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\ImportLog;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Importers\Contracts\CsvImporterContract;
use App\Importers\Contracts\HandlesUploadedFile;

/**
 * Wählt zur Hersteller-ID den passenden Importer und bietet einen
 * einheitlichen Dispatcher für CSV/XML/API-Importe.
 */
class ImporterSelector
{
  /**
   * Liefert den passenden Importer zur übergebenen Hersteller-ID.
   *
   * Ermittelt aus Hersteller-Slug/-Name einen Normalized Key (z. B. "edelrid")
   * und instanziiert die entsprechende Importer-Klasse (inkl. evtl. benötigter
   * Konstruktor-Parameter wie manufacturerId).
   *
   * @param  int|string  $manufacturerId  Primärschlüssel des Herstellers
   * @return object  Konkrete Importer-Instanz für diesen Hersteller
   *
   * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
   * @throws \InvalidArgumentException
   */
  public static function forManufacturer(int|string $manufacturerId): CsvImporterContract|HandlesUploadedFile
  {
    $m = Manufacturer::query()->findOrFail($manufacturerId);
    $short = Str::before(Str::slug((string)($m->slug ?: $m->manufacturer ?: '')), '-');

    /**
     * ! Über Flag steuern !!!
     */
    
    ImportLog::debug('ImporterSelector resolving', [
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
   * Führt den Import für die gegebene Importer-Instanz und Quelle aus.
   *
   * Erkennt automatisch, ob der Importer CSV (Pfad), Upload-Objekte,
   * XML- oder API-Methoden unterstützt, und ruft die passende Methode auf.
   *
   * @param  object            $importer  Hersteller-spezifischer Importer
   * @param  string            $type      'csv'|'xml'|'api'
   * @param  mixed             $source    CSV: string Pfad; XML/API: Quelle (URL/Path/Stream)
   * @return void
   *
   * @throws \RuntimeException|\InvalidArgumentException
   */
  public static function handleImport(object $importer, string $type, mixed $source): void
  {
    ImportLog::debug('ImporterSelector.handleImport ENTER', [
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
