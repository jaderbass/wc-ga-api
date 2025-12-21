<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeProductsCommand extends Command
{
  protected $signature = 'products:purge
    {--manufacturer= : Hersteller-ID (optional). Wenn gesetzt, werden nur Produkte dieses Herstellers gelöscht.}
    {--force : Ohne Rückfrage ausführen.}';

  protected $description = 'Löscht Produkte inkl. Variationen/Meta/Pivot (optional je Hersteller).';

  public function handle(): int
  {
    $manufacturerId = $this->option('manufacturer');
    $force = (bool) $this->option('force');

    if (! $force) {
      $text = $manufacturerId
        ? "Wirklich ALLE Produkte von manufacturer_id={$manufacturerId} löschen (inkl. Variationen/Meta/Pivot)?"
        : 'Wirklich ALLE Produkte löschen (inkl. Variationen/Meta/Pivot)?';

      if (! $this->confirm($text)) {
        $this->info('Abgebrochen.');
        return self::SUCCESS;
      }
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    if ($manufacturerId) {
      $mid = (int) $manufacturerId;

      DB::statement("
        DELETE piv FROM product_variation_attribute_value piv
        JOIN product_variations v ON v.id = piv.product_variation_id
        JOIN products p ON p.id = v.product_id
        WHERE p.manufacturer_id = {$mid}
      ");

      DB::statement("
        DELETE pm FROM product_meta pm
        JOIN products p ON p.id = pm.product_id
        WHERE p.manufacturer_id = {$mid}
      ");

      DB::statement("
        DELETE pm FROM product_meta pm
        JOIN product_variations v ON v.id = pm.variation_id
        JOIN products p ON p.id = v.product_id
        WHERE p.manufacturer_id = {$mid}
      ");

      DB::statement("
        DELETE pi FROM product_images pi
        JOIN products p ON p.id = pi.product_id
        WHERE p.manufacturer_id = {$mid}
      ");

      DB::statement("
        DELETE v FROM product_variations v
        JOIN products p ON p.id = v.product_id
        WHERE p.manufacturer_id = {$mid}
      ");

      DB::statement("DELETE FROM products WHERE manufacturer_id = {$mid}");
    } else {
      DB::statement('TRUNCATE TABLE product_variation_attribute_value');
      DB::statement('TRUNCATE TABLE product_meta');
      DB::statement('TRUNCATE TABLE product_images');
      DB::statement('TRUNCATE TABLE product_variations');
      DB::statement('TRUNCATE TABLE products');
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $this->info(
      $manufacturerId
        ? "Produkte für manufacturer_id={$manufacturerId} wurden gelöscht."
        : 'Alle Produkte wurden gelöscht.'
    );

    return self::SUCCESS;
  }
}
