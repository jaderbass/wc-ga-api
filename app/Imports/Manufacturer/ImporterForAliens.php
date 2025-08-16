<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Importer für Aliens-Produktdaten.
 *
 * Liest CSV-Dateien von Aliens ein, mappt die Werte auf die Products-Tabelle
 * und speichert sie in der Datenbank.
 * Der Produktname ("name") wird immer aus "Artikelbezeichnung" befüllt.
 */
class ImporterForAliens
{
  /**
   * Verarbeitet eine hochgeladene CSV-Datei und speichert Produkte.
   *
   * @param string $filePath Pfad zur hochgeladenen CSV-Datei im Storage
   * @return void
   */
  public function handleUploadedFile(string $filePath): void
  {
    Log::info('CSV-Import gestartet', [
      'importer' => self::class,
      'file' => $filePath,
      'model' => Product::class,
      'map' => [
        'productnumber' => 'Artikelnummer',
        'productname' => 'Artikelbezeichnung',
        'eancode' => 'EAN',
        'price' => 'eVK netto',
        'name' => 'Artikelbezeichnung',
      ],
    ]);

    $handle = fopen(storage_path('app/' . $filePath), 'r');
    $header = null;
    $rowCount = 0;

    while (($row = fgetcsv($handle, 1000, ';')) !== false) {
      if (!$header) {
        $header = $row;
        continue;
      }

      $row = array_combine($header, $row);
      $mappedData = $this->mapRow($row);
      $rowCount++;

      try {
        $product = Product::create($mappedData);
        Log::debug('Importiert', $product->toArray());
      } catch (\Throwable $e) {
        Log::error("Fehler beim Import in Zeile {$rowCount}", [
          'exception' => $e->getMessage(),
          'row' => $row
        ]);
      }
    }

    fclose($handle);

    Log::info('CSV-Import abgeschlossen', ['importierte_zeilen' => $rowCount]);
  }

  /**
   * Mappt eine CSV-Zeile auf die Felder der Products-Tabelle.
   *
   * @param array $row Array der CSV-Zeile (Spaltenname => Wert)
   * @return array Gemappte Produktdaten
   */
  private function mapRow(array $row): array
  {
    return [
      'product_name' => $row['Artikelbezeichnung'] ?? 'Unbenanntes Produkt',
      'product_number' => $row['Artikelnummer'] ?? null,
      'short_description' => $row['Artikelbezeichnung'] ?? null,
      'ean' => $row['EAN'] ?? null,
      'price' => $row['eVK netto'] ?? null,
      'manufacturer_id' => 1, // Aliens
      'slug' => $row['Artikelnummer'] ?? uniqid('produkt-'),
      'status' => 'draft',
    ];
  }
}
