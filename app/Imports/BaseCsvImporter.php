<?php

namespace App\Imports;

use League\Csv\Reader;
use Illuminate\Support\Facades\Log;
use App\Helpers\CsvHeaderMapper;

abstract class BaseCsvImporter
{
  /**
   * Muss das zu importierende Model zurückgeben.
   */
  abstract protected function model(): string;

  /**
   * Muss die Spalten-Mapping-Tabelle zurückgeben.
   */
  abstract protected function columnMap(): array;

  /**
   * Muss die fixen Werte (z. B. manufacturer_id) zurückgeben.
   */
  abstract protected function fixedValues(): array;

  /**
   * Kann von Unterklassen überschrieben werden, um Daten zu bereinigen.
   */
  protected function sanitize(array $data): array
  {
    return $data;
  }

  /**
   * Muss einen Datensatz upserten (update or create).
   */
  abstract protected function upsertRecord(array $data): void;

  public function handleUploadedFile(string $path): int
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);

    $count = 0;

    foreach ($csv as $rowNumber => $row) {
      try {
        $data = CsvHeaderMapper::remap($row, $this->columnMap());
        $data = $this->sanitize($data);
        $data = array_merge($data, $this->fixedValues());

        $this->upsertRecord($data);
        $count++;
      } catch (\Throwable $e) {
        Log::error("Fehler beim Import in Zeile {$rowNumber}", [
          'exception' => $e->getMessage(),
          'row' => $row,
        ]);
      }
    }

    Log::info("CSV-Import abgeschlossen: {$count} Datensätze verarbeitet.");
    return $count;
  }
}
