<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\Woo\VariationSyncService;

/**
 * WooSyncVariationsCommand
 *
 * CLI-Command zum Outbound-Sync von WooCommerce-Produktvarianten.
 * Nutzt den VariationSyncService und unterstützt Filter (IDs / alle),
 * Chunking sowie Fail-Hard-Verhalten.
 *
 * Usage:
 *  php artisan woo:sync:variations --id=123
 *  php artisan woo:sync:variations --ids=123,456,789
 *  php artisan woo:sync:variations --all
 *  php artisan woo:sync:variations --all --chunk=200
 *  php artisan woo:sync:variations --ids=10,11 --fail-hard
 *
 * Optionen:
 *  --id=ID                Synchronisiert genau ein Produkt (mehrfach nutzbar).
 *  --ids=ID1,ID2,...      Synchronisiert eine CSV-Liste von Produkt-IDs.
 *  --all                  Synchronisiert alle Produkte mit gesetzter woo_product_id.
 *  --chunk=NUM            DB-Chunk-Größe beim Laden der Produkte (Default 200).
 *  --fail-hard            Bricht bei HTTP-Fehlern ab (Exception).
 *
 * Hinweise:
 *  - Preise werden NICHT synchronisiert (siehe VariationPayloadBuilder).
 *  - Es werden nur Produkte mit products.woo_product_id != null berücksichtigt.
 *  - Produkte ohne lokale Varianten werden übersprungen.
 *
 * @author  JAderBass
 * @since   2025-09-19
 */
class WooSyncVariationsCommand extends Command
{
  /**
   * Die Signatur des Artisan-Commands.
   *
   * @var string
   */
  protected $signature = 'woo:sync:variations
        {--id=* : Sync exactly these product IDs (can be repeated)}
        {--ids= : CSV of product IDs}
        {--all : Sync all products with a woo_product_id}
        {--chunk=200 : Chunk size when loading products}
        {--fail-hard : Throw on HTTP/API errors}';

  /**
   * Kurze Beschreibung für php artisan list.
   *
   * @var string
   */
  protected $description = 'Sync WooCommerce product variations (outbound) using SKU match & create/update logic';

  /**
   * Handle: Einstiegspunkt des Commands.
   *
   * @param  VariationSyncService $service
   * @return int
   */
  public function handle(VariationSyncService $service): int
  {
    if (!config('woo.sync_enabled')) {
      $this->warn('Woo sync is disabled (config woo.sync_enabled = false). Aborting.');
      return self::SUCCESS;
    }

    $failHard = (bool) $this->option('fail-hard');
    $chunk    = (int) $this->option('chunk');

    /** @var array<int,string|int> $idsOpt */
    $idsOpt = (array) $this->option('id');
    /** @var string|null $idsCsv */
    $idsCsv = $this->option('ids');

    $ids = collect($idsOpt)->filter()->map(fn($v) => (int) $v);

    if (!empty($idsCsv)) {
      $ids = $ids->merge(
        collect(explode(',', $idsCsv))
          ->map(fn($v) => (int) trim($v))
          ->filter()
      );
    }

    $productsQuery = Product::query()
      ->whereNotNull('woo_product_id');

    if ($ids->isNotEmpty()) {
      $productsQuery->whereIn('id', $ids->unique()->values());
    } elseif (!$this->option('all')) {
      $this->error('No products selected. Use --id=, --ids= or --all.');
      return self::INVALID;
    }

    $total = (clone $productsQuery)->count();
    if ($total === 0) {
      $this->info('No products found to sync.');
      return self::SUCCESS;
    }

    $this->info("Starting variation sync for {$total} product(s) ...");

    $grand = [
      'products' => 0,
      'created' => 0,
      'updated' => 0,
      'skipped' => 0,
      'errors' => 0,
    ];
    $rows = [];

    $bar = $this->output->createProgressBar($total);
    $bar->start();

    $productsQuery->orderBy('id')->chunk($chunk, function (Collection $chunked) use ($service, $failHard, &$grand, &$rows, $bar) {
      /** @var Product $product */
      foreach ($chunked as $product) {
        $res = $service->syncProduct($product, $failHard);

        $grand['products']++;
        $grand['created'] += $res['created'];
        $grand['updated'] += $res['updated'];
        $grand['skipped'] += $res['skipped'];
        $grand['errors']  += $res['errors'];

        $rows[] = [
          'product_id'     => $product->id,
          'woo_product_id' => $product->woo_product_id,
          'created'        => $res['created'],
          'updated'        => $res['updated'],
          'skipped'        => $res['skipped'],
          'errors'         => $res['errors'],
        ];

        $bar->advance();
      }
    });

    $bar->finish();
    $this->newLine(2);

    // Ausgabe Tabelle
    $this->table(
      ['Product ID', 'Woo Product ID', 'Created', 'Updated', 'Skipped', 'Errors'],
      $rows
    );

    $this->line(sprintf(
      'Summary: products=%d, created=%d, updated=%d, skipped=%d, errors=%d',
      $grand['products'],
      $grand['created'],
      $grand['updated'],
      $grand['skipped'],
      $grand['errors']
    ));

    Log::info('woo:sync:variations summary', $grand);

    return $grand['errors'] > 0 ? self::FAILURE : self::SUCCESS;
  }
}
