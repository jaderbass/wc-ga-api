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
          // Orchestrator entscheidet PUT/POST + Invalid-ID-Recovery
          $res = $orch->syncSingle($product, false);

          $action = (string)($res['action'] ?? '');
          $status = (int)($res['status'] ?? 0);
          $remote = $res['remote_id'] ?? ($res['id'] ?? null);
          $error  = $res['body']['error'] ?? ($res['message'] ?? null);

          // neue Klassifizierung: alles mit -failed oder HTTP >= 400 ist Fehler
          $isFailed = str_ends_with($action, '-failed') || $status >= 400;

          if ($isFailed) {
            $fail++;
            $msg = $error ? " – {$error}" : '';
            $details[] = "✖ #{$product->id}: {$action}{$msg}";
            Log::error('SyncProductsBulkAction: update/create failed', [
              'product_id' => $product->id,
              'action'     => $action,
              'status'     => $status,
              'remote_id'  => $remote,
              'error'      => $error,
            ]);
          } elseif ($action === 'skipped' || !empty($res['skipped'])) {
            $skip++;
            $details[] = "⏭ #{$product->id}: unverändert";
          } else {
            $ok++;
            $act = $action ?: 'updated';
            $wid = $remote ?? '?';
            $details[] = "✔ #{$product->id} → {$act} (Woo #{$wid})";
            Log::info('SyncProductsBulkAction: success', [
              'product_id' => $product->id,
              'action'     => $act,
              'status'     => $status,
              'remote_id'  => $wid,
            ]);
          }
        } catch (\Throwable $e) {
          $fail++;
          $details[] = "✖ #{$product->id}: " . $e->getMessage();
          Log::error('SyncProductsBulkAction: exception', [
            'product_id' => $product->id,
            'message'    => $e->getMessage(),
          ]);
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
