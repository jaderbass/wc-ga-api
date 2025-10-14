<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Services\Woo\ProductExportOrchestrator;
use App\Services\Woo\VariationSyncService;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SyncVariationsBulkAction
 *
 * Filament Bulk Action zum Outbound-Sync von WooCommerce-Varianten.
 *
 * Usage:
 * - In ProductResource::table():
 *     use App\Filament\Resources\ProductResource\Actions\SyncVariationsBulkAction;
 *     Tables\Actions\BulkActionGroup::make([
 *         // ...
 *         SyncVariationsBulkAction::make('sync-variations'),
 *     ])
 *
 * Registration:
 * - Datei ablegen unter:
 *   app/Filament/Resources/ProductResource/Actions/SyncVariationsBulkAction.php
 * - Kein manuelles Registrieren notwendig; die Action wird in der Resource verwendet.
 *
 * Hinweise:
 * - Preise werden NICHT synchronisiert.
 * - Produkt muss eine woo_product_id haben.
 * - Varianten ohne SKU werden übersprungen.
 *
 * @author  JAderBass
 * @since   2025-09-19
 */
class SyncVariationsBulkAction extends BulkAction
{
  /**
   * Standard-Konfiguration der Action.
   *
   * Wir überschreiben NICHT die Signatur von ::make(),
   * sondern konfigurieren in setUp() → kompatibel mit Filament.
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Sync Variations to Woo')
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
    $orch    = app(ProductExportOrchestrator::class);
    $service = app(VariationSyncService::class);

    $summary = [
      'products' => 0,
      'created' => 0,
      'updated' => 0,
      'skipped' => 0,
      'errors' => 0,
    ];

    foreach ($records as $product) {
      /** @var Product $product */
      if (empty($product->woo_product_id)) {
        /* // Kein Erfolg/Fehler-Toast pro Item; nur finaler Status unten.
        Log::warning('SyncVariationsBulkAction: skipped product without woo_product_id', [
          'product_id' => $product->id,
        ]);
        $summary['skipped']++;
        continue; */
        // Neu: zuerst Parent sauber anlegen/aktualisieren (setzt type/attributes)
        $orch->syncSingle($product, failHard: false);
        $product->refresh(); // falls woo_product_id gesetzt wurde
        if (empty($product->woo_product_id)) {
          Log::warning('SyncVariationsBulkAction: still no woo_product_id after parent upsert', [
            'product_id' => $product->id,
          ]);
          $summary['skipped']++;
          continue;
        }
      }

      // Jetzt Variationen syncen (Parent ist variable + hat Attribute)
      $res = $service->syncProduct($product);

      $summary['products']++;
      $summary['created'] += $res['created'];
      $summary['updated'] += $res['updated'];
      $summary['skipped'] += $res['skipped'];
      $summary['errors']  += $res['errors'];
    }

    if ($summary['errors'] > 0) {
      $this->failureNotificationTitle("Woo sync completed with {$summary['errors']} error(s).");
    } else {
      $this->successNotificationTitle('Woo sync completed successfully.');
    }

    Log::info('SyncVariationsBulkAction summary', $summary);
  }
}
