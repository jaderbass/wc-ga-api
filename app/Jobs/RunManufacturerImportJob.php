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

  public function handle(): void
  {
    Log::info('Import job started', [
      'manufacturer_id' => $this->manufacturerId,
      'source_type'     => $this->sourceType,
      'source'          => $this->source,
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
  }
}
