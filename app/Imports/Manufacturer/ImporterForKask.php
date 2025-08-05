<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Importer für KASK-Produktdaten.
 *
 * Dieser Importer liest CSV-Dateien mit Produktdaten von KASK,
 * mappt die CSV-Spalten auf die Datenbankfelder der Products-Tabelle
 * und speichert sie. Maße und Gewichte werden als Strings übernommen.
 */
class ImporterForKask
{
  /**
   * Verarbeitet eine hochgeladene CSV-Datei.
   *
   * Liest die Datei zeilenweise ein, mappt die Werte und speichert sie
   * als neue Produkte in der Datenbank. Fehler werden im Log protokolliert.
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

    Log::info('CSV-Import abgeschlossen');
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
      'name' => $row['DESCRIPTION'] ?? 'Unbenanntes Produkt',
      'productnumber' => $row['PART #'] ?? null,
      'productname' => $row['DESCRIPTION'] ?? null,
      'eancode' => $row['EAN CODE'] ?? null,
      'width' => $row['SWIDHT'] ?? null,
      'length' => $row['SLENGHT'] ?? null,
      'height' => $row['SHEIGHT'] ?? null,
      'pcsperbox' => $row['PCS X BOX'] ?? null,
      'boxwidth' => $row['MWIDHT'] ?? null,
      'boxlength' => $row['MLENGHT'] ?? null,
      'boxheight' => $row['MHEIGHT'] ?? null,
      'weight' => $row['GROSS WEIGHT'] ?? null,
      'manufacturer_id' => 2,
      'slug' => $row['DESCRIPTION'] ?? uniqid('produkt-'),
      'status' => 'draft',
    ];
  }
}
