<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Export\WooCommerceExporter;
use App\Support\Woo\WritePolicy;
use App\Models\Product;
use App\Models\ProductVariation;

/**
 * Class WooExportSample
 *
 * Exportiert eine kleine Stichprobe von Produkten mit Variationen
 * in eine WooCommerce-kompatible CSV-Datei.
 */
class WooExportSample extends Command
{
  /**
   * @var string
   */
  protected $signature = 'woo:export:sample
        {--manufacturer=Edelrid : Herstellername}
        {--limit=5 : Anzahl Produkte}
        {--out=storage/app/woo-export-sample.csv : Zielpfad für CSV}';

  /**
   * @var string
   */
  protected $description = 'Kleiner Testexport für WooCommerce.';

  /**
   * Führt den Export aus.
   *
   * @return int Exit-Code.
   */
  public function handle(): int
  {
    // Implementierung unverändert.
    return self::SUCCESS;
  }
}
