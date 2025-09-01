<?php

namespace App\Importers\Contracts;

interface CsvImporterContract
{
  public function import(string $path): void;
}
