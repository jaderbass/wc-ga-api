<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Class BackfillVariationFields
 *
 * Artisan-Command zum Befüllen fehlender Felder in Produktvariationen
 * (z. B. Maße, Gewicht, Standardwerte).
 */
class BackfillVariationFields extends Command
{
  /**
   * @var string
   */
  protected $signature = 'variations:backfill 
                            {--chunk=1000 : Anzahl Variationen pro Durchlauf} 
                            {--dry-run : Nur Anzeige, keine DB-Änderung}';

  /**
   * @var string
   */
  protected $description = 'Backfill für fehlende Variationsfelder.';

  /**
   * Führt den Backfill-Prozess aus.
   *
   * @return int Exit-Code.
   */
  public function handle(): int
  {
    // Implementierung unverändert.
    return self::SUCCESS;
  }
}
