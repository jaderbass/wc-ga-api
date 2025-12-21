<?php

namespace App\Console\Commands;

use App\Services\ImporterSelector;
use Illuminate\Console\Command;

class SmokeTestAliensImportSyncCommand extends Command
{
  protected $signature = 'import:smoke-aliens-sync
    {--manufacturer=1 : Manufacturer ID (default: 1)}
    {--file= : Absolute path to CSV file (required)}
    {--author=1 : Author/User ID (default: 1)}';

  protected $description = 'Smoke test: run Aliens import synchronously (no queue)';

  public function handle(): int
  {
    $manufacturerId = (int) $this->option('manufacturer');
    $file = (string) $this->option('file');
    $authorId = (int) $this->option('author');

    if ($file === '') {
      $this->error('Missing --file option (absolute path).');
      return self::FAILURE;
    }

    if (!is_file($file) || !is_readable($file)) {
      $this->error("File not accessible: {$file}");
      return self::FAILURE;
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
