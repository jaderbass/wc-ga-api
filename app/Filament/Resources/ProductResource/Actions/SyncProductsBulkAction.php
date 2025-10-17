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
 * Filament Bulk Action zum Outbound-Sync von WooCommerce-Hauptprodukten
 * (simple & variable) – ohne Preise. Für Varianten existiert separat
 * die SyncVariationsBulkAction.
 *
 * Usage:
 * - In ProductResource::table():
 *     use App\Filament\Resources\ProductResource\Actions\SyncProductsBulkAction;
 *     Tables\Actions\BulkActionGroup::make([
 *         // ...
 *         SyncProductsBulkAction::make('sync-products'),
 *     ])
 *
 * Hinweise:
 * - Legt Parent-Produkte in Woo an bzw. aktualisiert diese (Create/Update,
 *   abhängig von Preflight/woo_product_id).
 * - Bei "variable" wird bewusst KEINE Parent-SKU gesetzt (Best Practice).
 * - Preise werden NICHT synchronisiert.
 *
 * @author  JAderBass
 * @since   2025-10-17
 */
class SyncProductsBulkAction extends BulkAction
{
  /**
   * Standard-Konfiguration der Action.
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
    /** @var ProductExportOrchestrator $orch */
    $orch = app(ProductExportOrchestrator::class);

    $summary = [
      'products' => 0,
      'created'  => 0,
      'updated'  => 0,
      'skipped'  => 0,
      'errors'   => 0,
    ];

    foreach ($records as $product) {
      /** @var Product $product */
      $summary['products']++;

      try {
        // Orchestrierter Upsert des Hauptprodukts (mit Preflight)
        $res = $orch->syncSingle($product, failHard: false);

        $action = (string) ($res['action'] ?? 'skipped');
        if ($action === 'created') {
          $summary['created']++;
        } elseif ($action === 'updated') {
          $summary['updated']++;
        } elseif ($action === 'skipped') {
          $summary['skipped']++;
        } else {
          // z. B. 'error' oder unbekannt
          $summary['errors']++;
        }
      } catch (\Throwable $e) {
        $summary['errors']++;

        Log::error('SyncProductsBulkAction: error while syncing product', [
          'product_id' => $product->id,
          'message'    => $e->getMessage(),
        ]);
      }
    }

    // Benutzer-Feedback (nur ein finaler Toast)
    if ($summary['errors'] > 0) {
      $this->failureNotificationTitle("Woo parent sync: {$summary['products']} Produkte, {$summary['errors']} Fehler.");
    } else {
      $this->successNotificationTitle("Woo parent sync erfolgreich: {$summary['products']} Produkte (neu: {$summary['created']}, aktualisiert: {$summary['updated']}, übersprungen: {$summary['skipped']}).");
    }

    Log::info('SyncProductsBulkAction summary', $summary);
  }
}
