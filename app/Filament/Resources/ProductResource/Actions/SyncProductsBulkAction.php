<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Services\Woo\ProductExportOrchestrator;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SyncProductsBulkAction
 *
 * Filament Bulk Action zum Outbound-Sync von WooCommerce-Hauptprodukten.
 * Verwendet explizit den ProductExportOrchestrator (SKU-Preflight & sichere Parent-SKU-Regeln).
 *
 * Usage:
 * - In ProductResource::table() -> BulkActionGroup::make([ SyncProductsBulkAction::make('sync-products'), ... ])
 *
 * Hinweise:
 * - Parent-SKU-Regeln:
 *   * 'variable' Eltern -> niemals SKU setzen
 *   * 'simple' Eltern   -> products.sku, sonst Fallback products.product_number
 * - Nach erfolgreichem POST wird products.woo_product_id befüllt.
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class SyncProductsBulkAction extends BulkAction
{
  /**
   * Konfiguration der Action.
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Sync Products to Woo')
      ->icon('heroicon-o-arrow-up-on-square')
      ->requiresConfirmation()
      ->action(fn(Collection $records) => $this->handle($records));
  }

  /**
   * Führt den Sync für alle ausgewählten Produkte aus.
   *
   * @param  Collection<int,Product> $records
   * @return void
   */
  protected function handle(Collection $records): void
  {
    /** @var ProductExportOrchestrator $orchestrator */
    $orchestrator = app(ProductExportOrchestrator::class);

    $summary = ['products' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

    foreach ($records as $product) {
      /** @var Product $product */
      try {
        $res     = $orchestrator->syncSingle($product);
        $action  = $res['action'] ?? 'unknown';

        $summary['products']++;
        if ($action === 'created') $summary['created']++;
        if ($action === 'updated') $summary['updated']++;
        if ($action === 'error')   $summary['errors']++;
      } catch (\Throwable $e) {
        $summary['products']++;
        $summary['errors']++;
        Log::error('SyncProductsBulkAction exception', [
          'product_id' => $product->id,
          'error'      => $e->getMessage(),
        ]);
      }
    }

    if ($summary['errors'] > 0) {
      $this->failureNotificationTitle("Product sync completed with {$summary['errors']} error(s).");
    } else {
      $this->successNotificationTitle("Product sync completed. Created: {$summary['created']} · Updated: {$summary['updated']}");
    }

    Log::info('SyncProductsBulkAction summary', $summary);
  }
}
