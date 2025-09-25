<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Services\Woo\ProductExportOrchestrator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SyncProductsBulkAction
 *
 * Filament Bulk Action zum Outbound-Sync von WooCommerce-Hauptprodukten.
 * - Confirm-Formular: Shop-Profil, "Nur geänderte", Dry-run
 * - Nutzt ProductExportOrchestrator (SKU-Preflight & Parent-SKU-Regeln)
 * - Sendet explizite Filament-Notifications mit Zählerständen (created/updated/skipped/errors)
 *
 * UI-Fix:
 * - Select::make('shop') ist jetzt searchable() + native(false) → Tom Select
 *   Dadurch greift unser Dropdown-Styling (admin-overrides.css) zuverlässig.
 *
 * @author  JAderBass
 * @since   2025-09-25
 */
class SyncProductsBulkAction extends BulkAction
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Hauptprodukte synchronisieren')
      ->icon('heroicon-o-arrow-up-on-square')
      ->modalHeading('Hauptprodukte synchronisieren')
      ->requiresConfirmation()
      ->form([
        Select::make('shop')
          ->label('Shop')
          ->options($this->shopOptions())
          ->default($this->defaultShopKey())
          ->required()
          // --- UI-Fix: Tom Select aktivieren ---
          ->searchable()     // macht aus native <select> → Tom Select
          ->native(false)    // erzwingt JS-Select; unser CSS greift
          ->preload()        // lädt Optionen sofort (bessere UX)
          ->helperText('Ziel-Profil (definierbar unter woo.profiles in config/woo.php).'),

        Toggle::make('only_changed')
          ->label('Nur geänderte senden')
          ->default(true)
          ->helperText('Überspringt Produkte ohne Änderungen seit letztem Sync (benötigt Spalte woo_synced_at).'),

        Toggle::make('dry_run')
          ->label('Dry-run (nur Vorschau)')
          ->default(false)
          ->helperText('Kein Versand an Woo; nur Vorschau im Log.'),
      ])
      ->action(fn(Collection $records, array $data) => $this->handle($records, $data));
  }

  /**
   * Hauptlogik der Action inkl. Notification-Ausgabe.
   *
   * @param  Collection<int,Product> $records
   * @param  array{shop:string,only_changed:bool,dry_run:bool} $data
   */
  protected function handle(Collection $records, array $data): void
  {
    $this->applyShopProfile($data['shop'] ?? null);

    /** @var ProductExportOrchestrator $orchestrator */
    $orchestrator = app(ProductExportOrchestrator::class);

    $summary = ['products' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($records as $product) {
      /** @var Product $product */

      if (!empty($data['only_changed']) && $this->hasColumn($product, 'woo_synced_at')) {
        $last = $product->woo_synced_at;
        if ($last && $product->updated_at && $product->updated_at->lte($last)) {
          $summary['skipped']++;
          Log::info('SyncProductsBulkAction: skipped (no changes since last sync)', [
            'product_id'    => $product->id,
            'updated_at'    => (string) $product->updated_at,
            'woo_synced_at' => (string) $last,
          ]);
          continue;
        }
      }

      if (!empty($data['dry_run'])) {
        $type = $product->product_type ?: ($product->variations()->exists() ? 'variable' : 'simple');
        $skuSource = $type === 'variable'
          ? 'no-parent-sku (variable parent)'
          : (!empty($product->sku) ? 'products.sku' : (!empty($product->product_number) ? 'products.product_number' : 'none'));
        $skuValue = $type === 'variable' ? null : (!empty($product->sku) ? $product->sku : ($product->product_number ?? null));

        Log::info('SyncProductsBulkAction (dry-run): product preview', [
          'product_id' => $product->id,
          'name'       => $product->product_name ?? $product->name,
          'type'       => $type,
          'sku_source' => $skuSource,
          'sku_value'  => $skuValue,
        ]);
        $summary['products']++;
        continue;
      }

      try {
        $res    = $orchestrator->syncSingle($product);
        $action = $res['action'] ?? 'unknown';

        $summary['products']++;
        if ($action === 'created') $summary['created']++;
        if ($action === 'updated') $summary['updated']++;
        if ($action === 'error')   $summary['errors']++;

        if (!empty($data['only_changed']) && $this->hasColumn($product, 'woo_synced_at')) {
          $product->forceFill(['woo_synced_at' => now()])->saveQuietly();
        }
      } catch (\Throwable $e) {
        $summary['products']++;
        $summary['errors']++;
        Log::error('SyncProductsBulkAction exception', [
          'product_id' => $product->id,
          'error'      => $e->getMessage(),
        ]);
      }
    }

    $title = $data['dry_run'] ? 'Dry-run abgeschlossen' : 'Woo-Sync abgeschlossen';
    $body  = sprintf(
      "Gesamt: %d\nErstellt: %d · Aktualisiert: %d · Übersprungen: %d · Fehler: %d",
      $summary['products'],
      $summary['created'],
      $summary['updated'],
      $summary['skipped'],
      $summary['errors']
    );

    if ($summary['errors'] > 0) {
      Notification::make()->title($title)->body($body)->danger()->send();
    } else {
      Notification::make()->title($title)->body($body)->success()->send();
    }

    Log::info('SyncProductsBulkAction summary', $summary);
  }

  // ----------------- Hilfsfunktionen ------------------------

  protected function shopOptions(): array
  {
    $profiles = (array) config('woo.profiles', []);
    if (empty($profiles)) return ['default' => 'Default'];

    $opts = [];
    foreach ($profiles as $key => $_) {
      $opts[$key] = ucfirst((string) $key);
    }
    return $opts;
  }

  protected function defaultShopKey(): string
  {
    $profiles = (array) config('woo.profiles', []);
    if (empty($profiles)) return 'default';
    if (isset($profiles['staging'])) return 'staging';
    if (isset($profiles['production'])) return 'production';
    return array_key_first($profiles);
  }

  protected function applyShopProfile(?string $key): void
  {
    $profiles = (array) config('woo.profiles', []);
    if (empty($key) || $key === 'default' || empty($profiles[$key])) {
      return;
    }
    $p = $profiles[$key];
    if (!empty($p['base_url'])) config()->set('woo.api.base_url', $p['base_url']);
    if (!empty($p['key']))      config()->set('woo.api.key', $p['key']);
    if (!empty($p['secret']))   config()->set('woo.api.secret', $p['secret']);
    Log::info('SyncProductsBulkAction: applied shop profile', ['profile' => $key]);
  }

  protected function hasColumn(Product $product, string $column): bool
  {
    return Schema::hasColumn($product->getTable(), $column);
  }
}
