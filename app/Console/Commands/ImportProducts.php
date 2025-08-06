<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\GenericCsvProductImporter;

/**
 * Class ImportProducts
 *
 * CLI-Kommando zum Importieren von Produktdaten aus einer CSV-Datei mithilfe eines Mapping-Profils.
 * Erwartet zwei Argumente:
 *   - mapping: Der Name der Mapping-Konfiguration (z. B. "petzl")
 *   - file: Der Pfad zur CSV-Datei
 *
 * Beispiel:
 *   php artisan import:products petzl /pfad/zur/datei.csv
 */
class ImportProducts extends Command
{
  /**
   * Der Name und die Signatur des Konsolenkommandos.
   *
   * @var string
   */
  protected $signature = 'import:products {mapping} {file}';

  /**
   * Die Beschreibung des Konsolenkommandos.
   *
   * @var string
   */
  protected $description = 'Import products from a CSV file using a mapping';

  /**
   * Führt das Konsolenkommando aus.
   *
   * @return int
   */
  public function handle(): int
  {
    $mapping = $this->argument('mapping');
    $file = $this->argument('file');

    (new GenericCsvProductImporter($mapping))->import($file);

    $this->info("Import completed for mapping [$mapping].");

    return self::SUCCESS;
  }
}
