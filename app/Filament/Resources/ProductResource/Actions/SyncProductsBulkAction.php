<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\WooProductService;
use Carbon\Carbon;
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

      $selectedIds = $records->pluck('id')->values()->all();
      Log::info('SyncProductsBulkAction: starting with selected IDs', [
        'count' => count($selectedIds),
        'ids'   => $selectedIds,
      ]);

      foreach ($records as $product) {
        try {
          $product->refresh();

          if (!in_array($product->id, $selectedIds, true)) {
            // Sollte nie passieren, aber falls doch: NICHT senden.
            $skip++;
            Log::warning('SyncProductsBulkAction: product not in selectedIds -> skipped', [
              'product_id' => $product->id,
            ]);
            continue;
          }


          // --- Guard: only_changed -> updated_at muss > woo_synced_at sein ---
          $onlyChanged = (bool)($data['only_changed'] ?? false);
          if ($onlyChanged) {
            $lastSync = $product->last_synced_at ? Carbon::parse($product->last_synced_at) : null;
            $updated  = $product->updated_at     ? Carbon::parse($product->updated_at)     : null;

            if ($lastSync && $updated && $updated->lte($lastSync)) {
              $skip++;
              $details[] = "⏭ #{$product->id}: unverändert (updated_at ≤ last_synced_at)";
              Log::info('SyncProductsBulkAction: skipped unchanged (timestamp guard)', [
                'product_id'     => $product->id,
                'updated_at'     => $updated?->toDateTimeString(),
                'last_synced_at' => $lastSync?->toDateTimeString(),
              ]);
              continue; // nichts schicken
            }
          }

          // Orchestrator entscheidet PUT/POST + Invalid-ID-Recovery
          $res = $orch->syncSingle($product, false);

          $action = (string)($res['action'] ?? '');
          $status = (int)($res['status'] ?? 0);
          $remote = $this->extractRemoteId($res);
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
          } elseif ($action === 'skipped' || !empty($res['skipped']) || !$remote) {
            // Ohne Remote-ID NICHT als Erfolg zählen → skipped
            $skip++;
            $reason = $action === 'skipped' ? 'unverändert' : 'keine remote_id';
            $details[] = "⏭ #{$product->id}: {$reason}";
            Log::warning('SyncProductsBulkAction: response without id — skipped', [
              'product_id'   => $product->id,
              'action'       => $action,
              'status'       => $status,
              'has_remoteId' => (bool) $remote,
            ]);
          } else {
            $ok++;
            $act = $action ?: 'updated';
            $wid = $remote;
            $details[] = "✔ #{$product->id} → {$act} (Woo #{$wid})";
            Log::info('SyncProductsBulkAction: success', [
              'product_id' => $product->id,
              'action'     => $act,
              'status'     => $status,
              'remote_id'  => $wid,
            ]);
          }

          // Nach erfolgreichem created/updated:
          if (!empty($remote)) {
            $product->last_synced_at = now();
            $product->save();
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

    $selectedIds = $records->pluck('id')->values()->all();
    Log::info('SyncProductsBulkAction: starting with selected IDs', [
      'count' => count($selectedIds),
      'ids'   => $selectedIds,
    ]);

    foreach ($records as $product) {
      /** @var Product $product */
      // Sicherstellen, dass das Model frisch aus der DB geladen ist
      $product->refresh();

      // --- Guard: only_changed -> updated_at muss > woo_synced_at sein ---
      $onlyChanged = (bool)($data['only_changed'] ?? false);
      if ($onlyChanged) {
        $lastSync = $product->last_synced_at ? Carbon::parse($product->last_synced_at) : null;
        $updated  = $product->updated_at     ? Carbon::parse($product->updated_at)     : null;

        if ($lastSync && $updated && $updated->lte($lastSync)) {
          $summary['skipped']++;
          Log::info('SyncProductsBulkAction: skipped unchanged (timestamp guard)', [
            'product_id'     => $product->id,
            'updated_at'     => $updated?->toDateTimeString(),
            'last_synced_at' => $lastSync?->toDateTimeString(),
          ]);
          continue;
        }
      }

      $summary['products']++;

      try {
        // Orchestrierter Upsert des Hauptprodukts (mit Preflight)
        $res = $orch->syncSingle($product, failHard: false);

        $action   = (string) ($res['action'] ?? 'skipped');
        $remoteId = $this->extractRemoteId($res);
        $status   = (int)($res['status'] ?? 0);

        if (str_ends_with($action, '-failed') || $status >= 400) {
          $summary['errors']++;
          Log::error('SyncProductsBulkAction: error while syncing product', [
            'product_id' => $product->id,
            'action'     => $action,
            'status'     => $status,
            'remote_id'  => $remoteId,
          ]);
        } elseif (($action === 'created' || $action === 'updated') && $remoteId) {
          // Nur als Erfolg zählen, wenn wirklich eine ID zurückkam
          $summary[$action]++; // 'created' oder 'updated'
          Log::info('SyncProductsBulkAction: success', [
            'product_id' => $product->id,
            'action'     => $action,
            'remote_id'  => $remoteId,
          ]);
          // Nach erfolgreichem created/updated:
          if (!empty($remoteId)) {
            $product->last_synced_at = now();
            $product->save();
          }
        } else {
          // alles andere (inkl. created/updated OHNE id) → skipped
          $summary['skipped']++;
          Log::warning('SyncProductsBulkAction: skipped (no changes or no id)', [
            'product_id'  => $product->id,
            'action'      => $action,
            'has_remoteId' => (bool)$remoteId,
          ]);
        }

        // Nach erfolgreichem created/updated:
        if (!empty($remote)) {
          $product->woo_synced_at = now();
          $product->save();
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

  /**
   * Extrahiert die Woo-Remote-ID aus gemischten Service-Responses.
   */
  private function extractRemoteId(mixed $res): ?int
  {
    if (is_array($res)) {
      $id = $res['remote_id'] ?? $res['id'] ?? ($res['body']['id'] ?? null);
    } elseif (is_object($res)) {
      $id = $res->remote_id ?? $res->id ?? null;
    } else {
      $id = null;
    }
    return $id ? (int) $id : null;
  }
}
