<?php

namespace App\Console\Commands;

use App\Services\ImporterSelector;
use Illuminate\Console\Command;

class SmokeTestAliensImportSyncCommand extends Command
{
  protected $signature = 'import:smoke-aliens-sync
    {--manufacturer=1 : Manufacturer ID}
    {--file= : Absolute path to CSV file (optional, defaults to latest import)}
    {--author=1 : Author/User ID}';

  protected $description = 'Smoke test: run Aliens import synchronously (no queue)';

  public function handle(): int
  {
    $manufacturerId = (int) $this->option('manufacturer');
    $authorId = (int) $this->option('author');
    $fileOption = $this->option('file');

    if ($fileOption) {
      $file = (string) $fileOption;
      if (!is_file($file) || !is_readable($file)) {
        $this->error("File not accessible: {$file}");
        return self::FAILURE;
      }
    } else {
      $importDir = storage_path('app/imports');
      $files = glob($importDir . '/*.csv');

      if (!$files) {
        $this->error('No CSV files found in storage/app/imports');
        return self::FAILURE;
      }

      // neueste Datei nehmen
      usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
      $file = $files[0];

      $this->info('No --file given, using latest CSV:');
      $this->line($file);
    }


    $this->info('Resolving importer...');
    $importer = ImporterSelector::forManufacturer($manufacturerId);

    if (method_exists($importer, 'setAuthorId')) {
      $importer->setAuthorId($authorId);
    }

    $this->info('Running import sync...');
    ImporterSelector::handleImport($importer, 'csv', $file);

    $this->info('Import finished.');
    return self::SUCCESS;
  }
}
