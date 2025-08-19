<?php

namespace App\Importers\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Support\Facades\Log;

/**
 * ImporterForEdelrid
 *
 * Hersteller-spezifischer CSV-Importer für EDELRID (CSV).
 * Struktur ist absichtlich identisch zu ImporterForPetzl:
 *  - Konstruktor gibt Mapping-Name und Hersteller-ID an die Basisklasse weiter
 *  - handleUploadedFile() loggt und delegiert an import($filePath)
 *
 * Voraussetzungen:
 *  - Mapping-Datei: config/import_mappings/edelrid.php
 *  - GenericCsvProductImporter::import(string $filePath): void
 */
class ImporterForEdelrid extends GenericCsvProductImporter
{
  /**
   * Konstruktor – analog zu ImporterForPetzl.
   *
   * @param int|null $manufacturerId  Optionale Hersteller-ID zur Verknüpfung
   */
  public function __construct(?int $manufacturerId = null)
  {
    // mappingFile = 'edelrid' -> lädt config/import_mappings/edelrid.php
    parent::__construct('edelrid', $manufacturerId);
  }

  /**
   * Verarbeitung einer hochgeladenen CSV-Datei – analog zu ImporterForPetzl.
   *
   * @param string $filePath  Vollständiger Pfad zur CSV-Datei
   * @return void
   */
  public function handleUploadedFile(string $filePath): void
  {
    Log::info('Edelrid-Import mit generischer Logik gestartet.', [
      'file' => $filePath,
      'manufacturer_id' => $this->manufacturerId,
    ]);

    // Die eigentliche Import-Logik wird von der Basisklasse ausgeführt.
    $this->import($filePath);

    Log::info('Edelrid-Import abgeschlossen.');
  }
}
