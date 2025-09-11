<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Class BackfillProductFields
 *
 * Artisan-Command zum Befüllen fehlender Produktfelder
 * (z. B. Maße, Gewicht, Standardwerte).
 */
class BackfillProductFields extends Command
{
  /**
   * @var string
   */
  protected $signature = 'products:backfill 
                            {--chunk=1000 : Anzahl Produkte pro Durchlauf} 
                            {--dry-run : Nur Anzeige, keine DB-Änderung}';

  /**
   * @var string
   */
  protected $description = 'Backfill für fehlende Produktfelder.';

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
