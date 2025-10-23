<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Woo\ProductExportOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SmokeWooProductSyncCommand extends Command
{
  protected $signature = 'app:smoke-woo-product
        {--product= : ID des lokalen Parent-Produkts}
        {--invalidate : Setzt woo_product_id absichtlich auf eine ungültige ID (simuliert 400 invalid_id)}
        {--nullify : Setzt woo_product_id auf NULL (erzwingt Create)}
        {--restore= : Alte Woo-ID wiederherstellen (numerischer Wert)}
        {--fail-hard : Exceptions nicht abfangen (zum Debuggen)}';

  protected $description = 'Smoke-Test für den Parent-Product-Sync (Invalid-ID-Recovery & Create/Update-Pfade)';

  public function handle(): int
  {
    $id = (int) ($this->option('product') ?? 0);
    if ($id <= 0) {
      $this->error('Bitte --product=<ID> angeben.');
      return self::FAILURE;
    }

    /** @var Product|null $product */
    $product = Product::query()->find($id);
    if (!$product) {
      $this->error("Produkt #{$id} nicht gefunden.");
      return self::FAILURE;
    }

    $origWooId = $product->woo_product_id;

    // Optional: restore explizite ID
    if ($this->option('restore') !== null) {
      $restoreId = (int) $this->option('restore');
      $product->woo_product_id = $restoreId > 0 ? $restoreId : null;
      $product->save();
      $this->info("Wiederhergestellt: products.woo_product_id = " . var_export($product->woo_product_id, true));
    }

    // Optional: auf NULL setzen (erzwingt Create)
    if ($this->option('nullify')) {
      $product->woo_product_id = null;
      $product->save();
      $this->info('Erzwinge Create: products.woo_product_id = NULL');
    }

    // Optional: ungültige ID simulieren (erzwingt Invalid-ID-Recovery im Orchestrator)
    if ($this->option('invalidate')) {
      $bad = (int) ($product->woo_product_id ?: 0) + 999999;
      if ($bad <= 0) {
        $bad = 999999999;
      }
      $product->woo_product_id = $bad;
      $product->save();
      $this->warn("Simuliere ungültige ID: products.woo_product_id = {$bad}");
    }

    $failHard = (bool) $this->option('fail-hard');
    $this->line("Starte Orchestrator-Sync für Produkt #{$product->id} (failHard=" . ($failHard ? 'true' : 'false') . ') …');

    /** @var ProductExportOrchestrator $orch */
    $orch = app(ProductExportOrchestrator::class);

    try {
      $res = $orch->syncSingle($product, $failHard);

      // Konsolidierte Ausgabe
      $summary = [
        'product_id' => $product->id,
        'orig_woo_id' => $origWooId,
        'current_woo_id' => $product->woo_product_id,
        'action' => $res['action'] ?? ($res['status'] ?? 'ok'),
        'remote_id' => $res['remote_id'] ?? ($res['id'] ?? null),
        'created' => $res['created'] ?? null,
        'updated' => $res['updated'] ?? null,
        'skipped' => $res['skipped'] ?? null,
        'errors' => $res['errors'] ?? null,
        'message' => $res['message'] ?? null,
      ];

      $this->info('Ergebnis:');
      $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

      Log::info('SmokeWooProductSync summary', $summary);

      // Quick-Hinweis bei erfolgreicher Invalid-ID-Recovery
      if ($origWooId && $origWooId !== $product->woo_product_id) {
        $this->comment("Hinweis: Woo-ID geändert {$origWooId} → {$product->woo_product_id} (Invalid-ID-Recovery).");
      }

      return self::SUCCESS;
    } catch (\Throwable $e) {
      $this->error('Sync Exception: ' . $e->getMessage());
      Log::error('SmokeWooProductSync exception', [
        'product_id' => $product->id,
        'message' => $e->getMessage(),
      ]);
      return self::FAILURE;
    }
  }
}
