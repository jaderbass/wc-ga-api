<?php

namespace App\Imports;

use League\Csv\Reader;
use Illuminate\Support\Facades\Log;
use App\Helpers\CsvHeaderMapper;
use App\Helpers\CsvValueSanitizer;

/**
 * Basisklasse für CSV-Importe.
 *
 * Bietet Methoden zum Einlesen, Mappen und Bereinigen von CSV-Daten
 * sowie zum Erstellen oder Aktualisieren von Datensätzen in der Datenbank.
 * Hersteller-Importer erben von dieser Klasse und implementieren
 * die herstellerspezifische Logik.
 */
abstract class BaseCsvImporter
{
  /**
   * Gibt den Eloquent-Modellklassennamen zurück, auf den der Import zielt.
   *
   * Muss von der abgeleiteten Klasse implementiert werden.
   *
   * @return string Vollqualifizierter Klassenname (z. B. App\\Models\\Product::class)
   */
  abstract protected function model(): string;

  /**
   * Gibt ein Mapping zwischen Datenbankfeldern und CSV-Spalten zurück.
   *
   * Muss von der abgeleiteten Klasse implementiert werden.
   *
   * @return array Assoziatives Array im Format [DB-Feld => CSV-Spaltenname]
   */
  abstract protected function columnMap(): array;

  /**
   * Gibt zusätzliche feste Werte zurück, die beim Import gesetzt werden sollen.
   *
   * Wird in der abgeleiteten Klasse überschrieben, um z. B. eine Hersteller-ID
   * oder Standardwerte zu setzen.
   *
   * @return array Key-Value-Paare fester Werte.
   */
  abstract protected function fixedValues(): array;

  /**
   * Verarbeitet eine hochgeladene CSV-Datei.
   *
   * @param string $path Relativer Pfad zur CSV-Datei im Storage.
   * @return void
   */
  public function handleUploadedFile(string $path): void
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);

    Log::info("CSV-Import gestartet", [
      'importer' => static::class,
      'file' => $path,
      'model' => $this->model(),
      'map' => $this->columnMap(),
    ]);

    foreach ($csv as $rowNumber => $row) {
      try {
        $data = CsvHeaderMapper::remap($row, $this->columnMap());
        $data = $this->sanitize($data);
        $data = array_merge($data, $this->fixedValues());

        Log::debug("Gemappte Daten", $data);

        $this->upsertRecord($data);
      } catch (\Throwable $e) {
        Log::error("Fehler beim Import in Zeile {$rowNumber}", [
          'exception' => $e->getMessage(),
          'row' => $row,
        ]);
      }
    }

    Log::info("CSV-Import abgeschlossen");
  }

  /**
   * Bereinigt und konvertiert die CSV-Datenwerte.
   *
   * @param array $data Assoziatives Array der gemappten CSV-Daten.
   * @return array Bereinigte Daten.
   */
  protected function sanitize(array $data): array
  {
    return collect($data)->map(function ($value, $key) {
      return match ($key) {
        'width', 'length', 'height',
        'boxwidth', 'boxlength', 'boxheight',
        'weight' => CsvValueSanitizer::toScaledInt($value, 100),
        default => CsvValueSanitizer::toNullableString($value),
      };
    })->all();
  }

  /**
   * Erstellt oder aktualisiert einen Datensatz in der Datenbank.
   *
   * @param array $data Bereinigte Daten für das Modell.
   * @return void
   */
  protected function upsertRecord(array $data): void
  {
    $model = $this->model();

    $record = $model::where('productnumber', $data['produc_tnumber'] ?? null)->first();

    if ($record) {
      $record->update($data);
      Log::info("Produkt aktualisiert", ['id' => $record->id]);
    } else {
      $model::create($data);
      Log::info("Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
    }
  }

  /**
   * Erstellt eine Vorschau der ersten Zeilen einer CSV-Datei.
   *
   * @param string $path Relativer Pfad zur CSV-Datei.
   * @param int $limit Anzahl der Vorschauzeilen.
   * @return array Array der bereinigten Vorschauzeilen.
   */
  public function preview(string $path, int $limit = 5): array
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);

    $rows = iterator_to_array($csv->getRecords());
    $previewRows = array_slice($rows, 0, $limit);

    return array_map(function ($row) {
      $mapped = CsvHeaderMapper::remap($row, $this->columnMap());
      $sanitized = $this->sanitize($mapped);
      return array_merge($sanitized, $this->fixedValues());
    }, $previewRows);
  }
}
