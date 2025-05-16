<?php

namespace App\Imports;

use League\Csv\Reader;
use Illuminate\Support\Facades\Log;
use App\Helpers\CsvHeaderMapper;
use App\Helpers\CsvValueSanitizer;

abstract class BaseCsvImporter
{
  abstract protected function model(): string;

  abstract protected function columnMap(): array;

  abstract protected function fixedValues(): array;

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

  protected function upsertRecord(array $data): void
  {
    $model = $this->model();

    $record = $model::where('productnumber', $data['productnumber'] ?? null)->first();

    if ($record) {
      $record->update($data);
      Log::info("Produkt aktualisiert", ['id' => $record->id]);
    } else {
      $model::create($data);
      Log::info("Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
    }
  }

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
