<?php

namespace App\Jobs;

use App\Services\ImporterSelector;
use App\Support\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Führt einen Hersteller-Import (csv/xml/api) außerhalb der HTTP-Request aus.
 *
 * Vorteil:
 * - UI bekommt sofort Antwort
 * - kein 500 durch Timeout/Headers
 * - Import läuft kontrolliert weiter (Queue oder afterResponse)
 */
class RunManufacturerImportJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /**
   * @param int    $manufacturerId
   * @param string $sourceType   csv|xml|api
   * @param string $source       Pfad (csv/xml) oder URL (api)
   */
  public function __construct(
    public int $manufacturerId,
    public string $sourceType,
    public string $source
  ) {}

  /**
   * Executes a manufacturer import in the background.
   *
   * This job resolves the correct importer for the given manufacturer and
   * delegates the import execution based on the source type.
   *
   * Source types:
   * - csv/xml: $this->source must be an absolute filesystem path
   * - api:     $this->source must be a URL
   *
   * Notes:
   * - The import can be memory intensive depending on the importer implementation.
   * - Any exception will be rethrown so the job can be marked as failed.
   *
   * @return void
   */
  public function handle(): void
  {
    try {
      Log::info('Import job started', [
        'manufacturer_id' => $this->manufacturerId,
        'source_type'     => $this->sourceType,
        'source'          => $this->source,
        'exists'          => in_array($this->sourceType, ['csv', 'xml'], true) ? file_exists($this->source) : null,
        'filesize'        => (in_array($this->sourceType, ['csv', 'xml'], true) && file_exists($this->source))
          ? filesize($this->source)
          : null,
        'memory_limit'    => ini_get('memory_limit'),
      ]);

      $importer = ImporterSelector::forManufacturer($this->manufacturerId);

      ImportLog::debug('[JOB] importer', [
        'manufacturer_id' => $this->manufacturerId,
        'class'           => get_debug_type($importer),
        'source_type'     => $this->sourceType,
      ]);

      ImporterSelector::handleImport($importer, $this->sourceType, $this->source);

      Log::info('Import job finished', [
        'manufacturer_id' => $this->manufacturerId,
        'source_type'     => $this->sourceType,
      ]);
    } catch (\Throwable $e) {
      Log::error('Import job failed', [
        'manufacturer_id' => $this->manufacturerId,
        'source_type'     => $this->sourceType,
        'source'          => $this->source,
        'msg'             => $e->getMessage(),
        'file'            => $e->getFile(),
        'line'            => $e->getLine(),
      ]);

      throw $e; // wichtig: damit Laravel es als failed job markieren kann
    }
  }
}
