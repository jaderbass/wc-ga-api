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
   * Konstruktor ohne Parameter (Petzl-ID fest 4, Mapping 'petzl').
   */
  public function __construct()
  {
    // Ruft den Konstruktor der Elternklasse auf und übergibt
    // den Namen der Mapping-Datei und die Hersteller-ID.
    parent::__construct('petzl', 4); // 'petzl' -> petzl.php, 4 -> Manufacturer ID
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
