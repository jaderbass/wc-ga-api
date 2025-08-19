<?php

namespace App\Imports\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Support\Facades\Log;

/**
 * Importer für Petzl-Produktdaten, der variable Produkte unterstützt.
 *
 * Diese Klasse erweitert den GenericCsvProductImporter und nutzt ein spezifisches
 * Mapping, um CSV-Dateien von Petzl korrekt zu verarbeiten. Sie gruppiert
 * Zeilen zu Hauptprodukten und legt die einzelnen Zeilen als Varianten an.
 */
class ImporterForPetzl extends GenericCsvProductImporter
{
  /**
   * Initialisiert den Importer mit dem Petzl-spezifischen Mapping.
   * Die Hersteller-ID für Petzl wird hier fest auf 4 gesetzt.
   */
  public function __construct()
  {
    // Ruft den Konstruktor der Elternklasse auf und übergibt
    // den Namen der Mapping-Datei und die Hersteller-ID.
    parent::__construct('petzl', 3); // 'petzl' -> petzl.php, 4 -> Manufacturer ID
  }

  /**
   * Verarbeitet die hochgeladene CSV-Datei.
   *
   * Diese Methode dient als öffentlicher Einstiegspunkt und ruft die
   * generische `import`-Methode der Elternklasse auf, die die gesamte
   * Logik für das Einlesen, Gruppieren und Speichern enthält.
   *
   * @param string $filePath Pfad zur hochgeladenen CSV-Datei im Storage.
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
