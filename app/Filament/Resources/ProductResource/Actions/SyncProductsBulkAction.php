<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\WooProductService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\Woo\ProductExportOrchestrator;

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

    /* $this->label('Sync Products to Woo')
      ->icon('heroicon-o-arrow-up-on-square')
      ->requiresConfirmation()
      ->action(fn(Collection $records) => $this->handle($records)); */
    $this->label('Produkt synchronisieren')
      ->icon('heroicon-o-arrow-up-on-square')
      ->deselectRecordsAfterCompletion()
      ->requiresConfirmation()
      ->form([
        Select::make('shop_id')
          ->label('Shop')
          ->options(Shop::query()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
          ->default(fn() => Shop::query()->where('is_default', true)->value('id'))
          ->required(),
        Toggle::make('only_changed')
          ->label('Nur geänderte senden')
          ->default(true),
        Toggle::make('dry_run')
          ->label('Dry-run (nur Vorschau)')
          ->default(false),
      ])
      ->action(function (Collection $records, array $data) {
        /** @var Shop $shop */
        $shop = Shop::findOrFail($data['shop_id']);
        /** @var ProductExportOrchestrator $orch */
        $orch = app(ProductExportOrchestrator::class);

        $ok = 0;
        $skip = 0;
        $fail = 0;
        $details = [];

        foreach ($records as $product) {
          try {
            // Orchestrator übernimmt Preflight (ungültige woo_product_id -> Create)
            // Falls Dein syncSingle keine Parameter für shop/dry_run/only_changed hat,
            // rufe es einfach ohne diese auf (siehe Fallback unten).
            $dryRun      = (bool)($data['dry_run'] ?? false);
            $onlyChanged = (bool)($data['only_changed'] ?? true);

            try {
              $res = $orch->syncSingle($product, $shop, $dryRun, $onlyChanged);
            } catch (\ArgumentCountError $sigMismatch) {
              // Rückwärtskompatibel: alte Signatur ohne diese Parameter
              $res = $orch->syncSingle($product);
            }

            if (($res['status'] ?? '') === 'error') {
              $fail++;
              $details[] = "✖ #{$product->id}: " . ($res['message'] ?? 'Unbekannter Fehler');
            } elseif (!empty($res['skipped'])) {
              $skip++;
              $details[] = "⏭ #{$product->id}: unverändert";
            } else {
              $ok++;
              $act = $res['action'] ?? 'update';
              $woo = $res['id'] ?? ($res['woo_product_id'] ?? '?');
              $details[] = "✔ #{$product->id} → {$act} (Woo #{$woo})";
            }
          } catch (\Throwable $e) {
            $fail++;
            $details[] = "✖ #{$product->id}: " . $e->getMessage();
          }
        }

        $summary = "OK: {$ok} · Übersprungen: {$skip} · Fehler: {$fail}";
        Notification::make()
          ->title('Woo-Sync abgeschlossen')
          ->body($summary . "\n" . implode("\n", array_slice($details, 0, 8)) . (count($details) > 8 ? "\n…" : ''))
          ->success()
          ->send();
      });
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
