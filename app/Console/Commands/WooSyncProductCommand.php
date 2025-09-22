<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Woo\ProductExportOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * WooSyncProductCommand
 *
 * CLI-Command zum Outbound-Sync von WooCommerce-Hauptprodukten
 * unter Nutzung des SKU-Preflights (ProductExportOrchestrator -> ProductUpsertService).
 *
 * Usage:
 *  php artisan woo:sync:product --id=123
 *  php artisan woo:sync:product --ids=10,11,12
 *  php artisan woo:sync:product --all --chunk=200
 *  php artisan woo:sync:product --id=123 --fail-hard
 *
 * Optionen:
 *  --id=*         Synchronisiert genau diese Produkt-IDs (kann mehrfach angegeben werden)
 *  --ids=         CSV-Liste von Produkt-IDs
 *  --all          Synchronisiert alle Produkte (Achtung: je nach Datenmenge)
 *  --chunk=200    Chunk-Größe beim Laden (nur relevant bei --all)
 *  --fail-hard    Wirft Exceptions bei HTTP-Fehlern (bricht den Lauf ab)
 *
 * Verhalten:
 *  - Baut einen konservativen Payload (ohne Preise).
 *  - Verwendet local products.product_type ('simple'|'variable') -> Woo 'type'.
 *  - Setzt Parent-SKU nur bei 'simple' (Best Practice).
 *  - Nach erfolgreichem Create (POST) wird products.woo_product_id gespeichert.
 *
 * Registration:
 *  - In app/Console/Kernel.php unter $commands[] eintragen:
 *      \App\Console\Commands\WooSyncProductCommand::class,
 *
 * Hinweise:
 *  - Für Varianten nutze weiterhin woo:sync:variations (bereits vorhanden).
 *  - Vor Erstinbetriebnahme .env/config für Woo prüfen (siehe README).
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class WooSyncProductCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'woo:sync:product
        {--id=* : Sync exactly these product IDs (can be repeated)}
        {--ids= : Comma separated list of product IDs}
        {--all : Sync all products}
        {--chunk=200 : Chunk size when loading products (for --all)}
        {--fail-hard : Throw on HTTP/API errors}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Sync WooCommerce parent products using SKU-preflight upsert (create/update).';

  /**
   * Execute the console command.
   */
  public function handle(ProductExportOrchestrator $orchestrator): int
  {
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

    $query = Product::query();

    if ($ids->isNotEmpty()) {
      $query->whereIn('id', $ids->unique()->values());
    } elseif (!$this->option('all')) {
      $this->error('No products selected. Use --id=, --ids= or --all.');
      return self::INVALID;
    }

    $total = (clone $query)->count();
    if ($total === 0) {
      $this->info('No products found to sync.');
      return self::SUCCESS;
    }

    $this->info("Starting parent product sync for {$total} product(s) ...");

    $bar = $this->output->createProgressBar($total);
    $bar->start();

    $rows = [];
    $summary = ['products' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

    $query->orderBy('id')->chunk($chunk, function (Collection $chunked) use ($orchestrator, $failHard, &$rows, &$summary, $bar) {
      /** @var Product $product */
      foreach ($chunked as $product) {
        try {
          $res = $orchestrator->syncSingle($product, $failHard);
          $action = $res['action'] ?? 'unknown';
          $status = (int) ($res['status'] ?? 0);
          $remote = $res['remote_id'] ?? null;

          $rows[] = [
            'id'            => $product->id,
            'woo_product_id' => $product->woo_product_id ?: $remote,
            'action'        => $action,
            'status'        => $status,
          ];

          $summary['products']++;
          if ($action === 'created') $summary['created']++;
          if ($action === 'updated') $summary['updated']++;
          if ($action === 'error')   $summary['errors']++;
        } catch (\Throwable $e) {
          $rows[] = [
            'id'            => $product->id,
            'woo_product_id' => $product->woo_product_id,
            'action'        => 'exception',
            'status'        => 0,
          ];
          $summary['products']++;
          $summary['errors']++;

          Log::error('woo:sync:product exception', [
            'product_id' => $product->id,
            'error'      => $e->getMessage(),
          ]);

          if ($failHard) {
            throw $e;
          }
        }

        $bar->advance();
      }
    });

    $bar->finish();
    $this->newLine(2);

    $this->table(['Product ID', 'Woo Product ID', 'Action', 'HTTP'], $rows);
    $this->line(sprintf(
      'Summary: products=%d, created=%d, updated=%d, errors=%d',
      $summary['products'],
      $summary['created'],
      $summary['updated'],
      $summary['errors']
    ));

    Log::info('woo:sync:product summary', $summary);

    return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
  }
}
