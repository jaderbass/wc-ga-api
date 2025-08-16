<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Importer für Petzl-Produktdaten.
 *
 * Liest CSV-Dateien von Petzl ein, mappt die Werte auf die Products-Tabelle
 * und speichert sie in der Datenbank.
 * Alle nicht vorhandenen Werte werden auf null gesetzt,
 * der Produktname ("name") wird immer aus "Product Name" gefüllt.
 */
class ImporterForPetzl
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
        'productnumber' => 'Reference',
        'productname' => 'Designation',
        'eancode' => 'EAN Code',
        'weight' => 'Weight',
        'manufacturercountry' => 'Country',
        'name' => 'Product Name',
        'description' => 'Description',
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
      'product_name' => $row['Product Name'] ?? 'Unbenanntes Produkt',       // Pflichtfeld
      'product_number' => $row['Reference'] ?? null,
      'short_description' => $row['Designation'] ?? null,
      'ean' => $row['EAN Code'] ?? null,
      'description' => $row['Description'] ?? null,
      'weight' => $row['Weight'] ?? null,
      'manufacturer_id' => 4,                                       // Petzl
      'slug' => $row['Reference'] ?? uniqid('produkt-'),
      'status' => 'draft',
    ];
  }
}
