<?php

namespace App\Console\Commands;

use App\Jobs\RunManufacturerImportJob;
use Illuminate\Console\Command;

class SmokeTestAliensImportCommand extends Command
{
  protected $signature = 'import:smoke-aliens
    {--manufacturer=1 : Manufacturer ID (default: 1)}
    {--file= : Absolute path to CSV file (required)}
    {--author=1 : Author/User ID to attribute created records to (default: 1)}
    {--queue=imports : Queue name (default: imports)}
    {--connection= : Queue connection override (default: queue.default)}';

  protected $description = 'Smoke test: dispatch Aliens CSV import job to the queue';

  public function handle(): int
  {
    $manufacturerId = (int) $this->option('manufacturer');
    $file = (string) $this->option('file');
    $authorId = (int) $this->option('author');
    $queue = (string) $this->option('queue');
    $connection = (string) ($this->option('connection') ?: config('queue.default', 'database'));

    if ($file === '') {
      $this->error('Missing --file option (absolute path).');
      return self::FAILURE;
    }

    if (!is_file($file)) {
      $this->error("File not found: {$file}");
      return self::FAILURE;
    }

    if (!is_readable($file)) {
      $this->error("File not readable: {$file}");
      return self::FAILURE;
    }

    $this->info('Dispatching import job...');
    $this->line("manufacturer_id: {$manufacturerId}");
    $this->line("source_type: csv");
    $this->line("file: {$file}");
    $this->line("author_id: {$authorId}");
    $this->line("connection: {$connection}");
    $this->line("queue: {$queue}");

    RunManufacturerImportJob::dispatch(
      $manufacturerId,
      'csv',
      $file,
      $authorId
    )->onConnection($connection)
      ->onQueue($queue);

    $this->info('Done. Now run a worker: php artisan queue:work ' . $connection . ' --queue=' . $queue . ' -v');

    return self::SUCCESS;
  }
}
