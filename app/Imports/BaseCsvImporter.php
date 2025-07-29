<?php

namespace App\Imports;

use League\Csv\Reader;

abstract class BaseCsvImporter extends BaseImporter
{
  /**
   * Lädt CSV-Datei und übergibt die Zeilen an BaseImporter::handle()
   */
  public function handleUploadedFile(string $path): void
  {
    $csv = Reader::createFromPath(storage_path("app/{$path}"), 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);

    $rows = iterator_to_array($csv->getRecords());

    $this->handle($rows);
  }
}
