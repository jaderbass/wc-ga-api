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
  public static function forManufacturer(int|string $manufacturerId): object
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

      case 'aliens':
        // Aliens gibt es doppelt:
        // - Aliens (CSV)  → GenericCsvProductImporter
        // - Aliens (API)  → SingingRock-Importer
        if ($m->import_type === 'api') {
          $instance = new \App\Imports\Manufacturer\ImporterForSingingRock(
            manufacturer: $m,
            manufacturerId: (int) $m->id,
          );
        } else {
          $instance = new \App\Importers\GenericCsvProductImporter(
            mappingFile: 'aliens',
            manufacturerId: (int) $m->id
          );
        }
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
   * @param  object $importer Hersteller-spezifischer Importer
   * @param  string $type     'csv'|'xml'|'api'
   * @param  mixed  $source   CSV: string Pfad; XML/API: Quelle (URL/Path/Stream)
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
      // CSV: bevorzugt Pfad → import($path)
      if (is_string($source)) {
        if (method_exists($importer, 'import')) {
          $importer->import($source);
          return;
        }

        if (method_exists($importer, 'handleUploadedFile')) {
          $importer->handleUploadedFile($source);
          return;
        }
      }

      // Fallback: Livewire-Upload-Objekt
      if (
        $source instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
        && method_exists($importer, 'handleUploadedFile')
      ) {
        $importer->handleUploadedFile($source);
        return;
      }

      throw new \RuntimeException(
        'CSV: Kein passender Entry-Point im Importer gefunden: ' . get_class($importer)
      );
    }

    if ($type === 'xml') {
      // XML: bevorzugt importFromXml(...)
      if (method_exists($importer, 'importFromXml')) {
        $importer->importFromXml($source);
        return;
      }

      // Alternative Signatur: handleUploadedXmlFile($path)
      if (method_exists($importer, 'handleUploadedXmlFile')) {
        $importer->handleUploadedXmlFile((string) $source);
        return;
      }

      throw new \InvalidArgumentException(
        'XML: Importer ' . get_class($importer) . ' unterstützt weder importFromXml noch handleUploadedXmlFile.'
      );
    }

    if ($type === 'api') {
      if (empty($source)) {
        throw new \InvalidArgumentException('API: Es wurde keine Quelle/URL übergeben.');
      }

      // API: bevorzugt importFromApi(...)
      if (method_exists($importer, 'importFromApi')) {
        $importer->importFromApi($source);
        return;
      }

      // Alternative Signatur: handleFromUrl($url) – z.B. Singing Rock
      if (method_exists($importer, 'handleFromUrl')) {
        $importer->handleFromUrl((string) $source);
        return;
      }

      throw new \InvalidArgumentException(
        'API: Importer ' . get_class($importer) . ' unterstützt keinen API-Import (weder importFromApi noch handleFromUrl).'
      );
    }

    throw new \InvalidArgumentException("Unbekannter Import-Typ: {$type}");
  }
}
