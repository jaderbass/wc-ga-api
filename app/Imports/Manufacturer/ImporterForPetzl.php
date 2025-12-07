<?php

namespace App\Imports\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Support\Facades\Log;

/**
 * Importer für Petzl-Produktdaten (CSV), unterstützt variable Produkte.
 *
 * Erweitert den GenericCsvProductImporter und verwendet ein spezifisches Mapping.
 * Gruppiert Zeilen zu Hauptprodukten und legt Varianten an.
 */

class ImporterForPetzl extends GenericCsvProductImporter
{
  /**
   * Konstruktor für den Petzl-Importer.
   *
   * @param  int  $manufacturerId  ID des Petzl-Herstellers (z.B. 4).
   */
  public function __construct(int $manufacturerId)
  {
    // Ruft den Konstruktor der Elternklasse auf und übergibt
    // den Namen der Mapping-Datei und die Hersteller-ID.
    // 'petzl' verweist auf config/import_mappings/petzl.php.
    parent::__construct('petzl', $manufacturerId);
  }

  /**
   * CSV-Import per Pfad starten (Delegation an Elternklasse).
   *
   * @param  string  $filePath
   * @return void
   */
  public function handleUploadedFile(string $filePath): void
  {
    Log::info('Petzl-Import mit generischer Logik gestartet.', [
      'file' => $filePath,
      'manufacturer_id' => $this->manufacturerId,
    ]);

    // Die eigentliche Import-Logik wird von der Elternklasse gehandhabt.
    $this->import($filePath);

    Log::info('Petzl-Import abgeschlossen.');
  }
}
