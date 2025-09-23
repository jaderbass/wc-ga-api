<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Services\Woo\VariationSyncService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SyncVariationsBulkAction
 *
 * Filament Bulk Action zum Outbound-Sync von WooCommerce-Varianten.
 * - Zeigt Confirm-Formular mit:
 *     * Shop-Profil (config('woo.profiles'))
 *     * "Nur geänderte senden"
 *     * Dry-run (nur Vorschau)
 * - Nutzt VariationSyncService (create/update per SKU)
 *
 * Delta-Logik ("Nur geänderte"):
 * - Wenn Produkt-Spalte 'woo_var_synced_at' existiert:
 *     - skipped, wenn product.updated_at <= woo_var_synced_at
 * - Zusätzlich, wenn Tabelle product_variations eine Spalte 'woo_synced_at' hat:
 *     - skipped, wenn KEINE Variation mit updated_at > woo_var_synced_at existiert
 * - Wenn keine der Spalten existiert → Option wird ignoriert (sicherer Fallback).
 *
 * Dry-run:
 * - Es werden keine Requests an Woo gesendet.
 * - Pro Produkt wird eine kompakte Vorschau geloggt (Anzahl Varianten, ohne SKU-Liste).
 *
 * Multi-Profile:
 * - Optional 'woo.profiles' in config/woo.php:
 *   'profiles' => [
 *     'staging' => ['base_url' => 'https://staging.tld', 'key' => 'ck...', 'secret' => 'cs...'],
 *     'production' => ['base_url' => 'https://shop.tld',    'key' => 'ck...', 'secret' => 'cs...'],
 *   ]
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class SyncVariationsBulkAction extends BulkAction
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Varianten synchronisieren')
      ->icon('heroicon-o-arrow-up-on-square')
      ->modalHeading('Varianten synchronisieren')
      ->requiresConfirmation()
      ->form([
        Select::make('shop')
          ->label('Shop')
          ->options($this->shopOptions())
          ->default($this->defaultShopKey())
          ->required()
          ->helperText('Profile definierbar unter woo.profiles in config/woo.php.'),

        Toggle::make('only_changed')
          ->label('Nur geänderte senden')
          ->default(true)
          ->helperText('Überspringt Produkte/Varianten ohne Änderungen seit letztem Varianten-Sync (wenn Spalten vorhanden).'),

        Toggle::make('dry_run')
          ->label('Dry-run (nur Vorschau)')
          ->default(false)
          ->helperText('Es werden keine Requests an Woo gesendet; nur Log-Vorschau pro Produkt.'),
      ])
      ->action(fn(Collection $records, array $data) => $this->handle($records, $data));
  }

  /**
   * Action-Handler.
   *
   * @param  Collection<int,Product>                          $records
   * @param  array{shop:string,only_changed:bool,dry_run:bool} $data
   * @return void
   */
  protected function handle(Collection $records, array $data): void
  {
    // Shop-Profil aktivieren (falls vorhanden)
    $this->applyShopProfile($data['shop'] ?? null);

    /** @var VariationSyncService $service */
    $service = app(VariationSyncService::class);

    $summary = ['products' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($records as $product) {
      /** @var Product $product */
      if (empty($product->woo_product_id)) {
        $summary['skipped']++;
        Log::warning('SyncVariationsBulkAction: skipped product without woo_product_id', [
          'product_id' => $product->id,
        ]);
        continue;
      }

      // „Nur geänderte“: prüfen, ob überhaupt Delta vorliegt
      if (!empty($data['only_changed']) && $this->shouldSkipAsUnchanged($product)) {
        $summary['skipped']++;
        Log::info('SyncVariationsBulkAction: skipped (no variant changes since last sync)', [
          'product_id' => $product->id,
        ]);
        continue;
      }

      // Dry-run?
      if (!empty($data['dry_run'])) {
        $count = $product->variations()->count();
        Log::info('SyncVariationsBulkAction (dry-run): product preview', [
          'product_id'      => $product->id,
          'woo_product_id'  => $product->woo_product_id,
          'variations_count' => $count,
          'note'            => 'No requests sent; run without dry_run to apply.',
        ]);
        $summary['products']++;
        continue;
      }

      // Echter Sync
      try {
        $res = $service->syncProduct($product, failHard: false);

        $summary['products']++;
        $summary['created'] += $res['created'] ?? 0;
        $summary['updated'] += $res['updated'] ?? 0;
        $summary['skipped'] += $res['skipped'] ?? 0;
        $summary['errors']  += $res['errors']  ?? 0;

        // Zeitstempel setzen, wenn „only_changed“ aktiv ist und Spalte existiert
        if (!empty($data['only_changed']) && $this->hasColumn($product->getTable(), 'woo_var_synced_at')) {
          $product->forceFill(['woo_var_synced_at' => now()])->saveQuietly();
        }
      } catch (\Throwable $e) {
        $summary['products']++;
        $summary['errors']++;
        Log::error('SyncVariationsBulkAction exception', [
          'product_id' => $product->id,
          'error'      => $e->getMessage(),
        ]);
      }
    }

    // Notification
    if ($summary['errors'] > 0) {
      $this->failureNotificationTitle("Varianten-Sync mit {$summary['errors']} Fehler(n).");
    } else {
      $this->successNotificationTitle(
        "Varianten-Sync ok. Created: {$summary['created']} · Updated: {$summary['updated']} · Skipped: {$summary['skipped']}"
      );
    }

    Log::info('SyncVariationsBulkAction summary', $summary);
  }

  /**
   * „Nur geänderte senden“ – Heuristik.
   * - Wenn 'woo_var_synced_at' auf products existiert:
   *   - skip, wenn product.updated_at <= woo_var_synced_at
   *   - falls Tabelle 'product_variations' existiert:
   *       skip, wenn es KEINE Variation mit updated_at > woo_var_synced_at gibt
   */
  protected function shouldSkipAsUnchanged(Product $product): bool
  {
    $hasProdCol = $this->hasColumn($product->getTable(), 'woo_var_synced_at');
    if (!$hasProdCol) {
      // keine Delta-Infos → nicht skippen
      return false;
    }

    $last = $product->woo_var_synced_at;
    if (!$last) {
      return false;
    }

    // Wenn Produkt selbst seitdem nicht geändert wurde und keine Variation neuer ist → skip
    $productUnchanged = $product->updated_at && $product->updated_at->lte($last);

    $hasVarTable = Schema::hasTable('product_variations');
    $varChanged = false;

    if ($hasVarTable) {
      // Gibt es eine Variation, die frischer ist als woo_var_synced_at?
      $varChanged = $product->variations()
        ->where('updated_at', '>', $last)
        ->exists();
    }

    return $productUnchanged && !$varChanged;
  }

  /**
   * Shop-Profile für die aktuelle Laufzeit setzen.
   */
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
    Log::info('SyncVariationsBulkAction: applied shop profile', ['profile' => $key]);
  }

  /**
   * Optionen für Shop-Select aus config('woo.profiles').
   *
   * @return array<string,string>
   */
  protected function shopOptions(): array
  {
    $profiles = (array) config('woo.profiles', []);
    if (empty($profiles)) {
      return ['default' => 'Default'];
    }
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

  protected function hasColumn(string $table, string $column): bool
  {
    return Schema::hasColumn($table, $column);
  }
}
