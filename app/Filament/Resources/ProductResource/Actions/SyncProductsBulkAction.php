<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Services\Woo\ProductExportOrchestrator;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SyncProductsBulkAction
 *
 * Filament Bulk Action zum Outbound-Sync von WooCommerce-Hauptprodukten.
 * - Nutzt den ProductExportOrchestrator (SKU-Preflight & Parent-SKU-Regeln).
 * - Zeigt ein Confirm-Formular mit:
 *     * Shop-Profil (z. B. "Staging"/"Production") – per config('woo.profiles')
 *     * "Nur geänderte senden"
 *     * Dry-run (nur Vorschau)
 *
 * Voraussetzung (optional, für Mehrfach-Profile):
 *  config/woo.php:
 *   'profiles' => [
 *      'staging' => ['base_url' => 'https://staging.tld', 'key' => 'ck_...', 'secret' => 'cs_...'],
 *      'production' => ['base_url' => 'https://shop.tld', 'key' => 'ck_...', 'secret' => 'cs_...'],
 *   ],
 *
 * Hinweise:
 * - "Nur geänderte senden": Greift nur, wenn am Product eine Spalte wie 'woo_synced_at' existiert.
 *   Dann werden Produkte übersprungen, deren updated_at <= woo_synced_at.
 * - Dry-run: Sendet NICHTS an Woo; wir loggen nur eine kompakte Vorschau (Name, Typ, Parent-SKU-Entscheidung).
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class SyncProductsBulkAction extends BulkAction
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Hauptprodukt synchronisieren')
      ->icon('heroicon-o-arrow-up-on-square')
      ->modalHeading('Hauptprodukt synchronisieren')
      ->requiresConfirmation()
      ->form([
        Select::make('shop')
          ->label('Shop')
          ->options($this->shopOptions())
          ->default($this->defaultShopKey())
          ->required()
          ->helperText('Wähle das Ziel-Profil. Profile können in config/woo.php unter woo.profiles definiert werden.'),

        Toggle::make('only_changed')
          ->label('Nur geänderte senden')
          ->default(true)
          ->helperText('Überspringt Produkte ohne Änderungen seit dem letzten Sync (benötigt Spalte woo_synced_at).'),

        Toggle::make('dry_run')
          ->label('Dry-run (nur Vorschau)')
          ->default(false)
          ->helperText('Es werden keine Requests an Woo gesendet; nur eine Vorschau wird geloggt.'),
      ])
      ->action(fn(Collection $records, array $data) => $this->handle($records, $data));
  }

  /**
   * Führt den Sync für alle ausgewählten Produkte aus.
   *
   * @param  Collection<int,Product> $records
   * @param  array{shop:string,only_changed:bool,dry_run:bool} $data
   * @return void
   */
  protected function handle(Collection $records, array $data): void
  {
    // 0) Shop-Profil temporär aktivieren (falls konfiguriert)
    $this->applyShopProfile($data['shop'] ?? null);

    /** @var ProductExportOrchestrator $orchestrator */
    $orchestrator = app(ProductExportOrchestrator::class);

    $summary = ['products' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($records as $product) {
      /** @var Product $product */

      // 1) Optional: nur geänderte senden (benötigt woo_synced_at)
      if (!empty($data['only_changed']) && $this->hasColumn($product, 'woo_synced_at')) {
        $last = $product->woo_synced_at;
        if ($last && $product->updated_at && $product->updated_at->lte($last)) {
          $summary['skipped']++;
          Log::info('SyncProductsBulkAction: skipped (no changes since last sync)', [
            'product_id' => $product->id,
            'updated_at' => (string) $product->updated_at,
            'woo_synced_at' => (string) $last,
          ]);
          continue;
        }
      }

      // 2) Dry-run? → nur Vorschau loggen, keinen HTTP-Call
      if (!empty($data['dry_run'])) {
        $type = $product->product_type ?: ($product->variations()->exists() ? 'variable' : 'simple');
        $skuDecision = $type === 'variable'
          ? 'no-parent-sku (variable parent)'
          : (!empty($product->sku) ? 'products.sku' : (!empty($product->product_number) ? 'products.product_number' : 'none'));
        $skuValue = $type === 'variable' ? null : (!empty($product->sku) ? $product->sku : ($product->product_number ?? null));

        Log::info('SyncProductsBulkAction (dry-run): product preview', [
          'product_id' => $product->id,
          'name'       => $product->product_name ?? $product->name,
          'type'       => $type,
          'sku_source' => $skuDecision,
          'sku_value'  => $skuValue,
        ]);
        $summary['products']++;
        continue;
      }

      // 3) Echter Sync via Orchestrator
      try {
        $res    = $orchestrator->syncSingle($product);
        $action = $res['action'] ?? 'unknown';

        $summary['products']++;
        if ($action === 'created') $summary['created']++;
        if ($action === 'updated') $summary['updated']++;
        if ($action === 'error')   $summary['errors']++;

        // Optional: Zeitstempel setzen, wenn only_changed aktiv ist
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

    // Ergebnis-Toast
    if ($summary['errors'] > 0) {
      $this->failureNotificationTitle("Sync abgeschlossen mit {$summary['errors']} Fehler(n).");
    } else {
      $this->successNotificationTitle("Sync abgeschlossen. Created: {$summary['created']} · Updated: {$summary['updated']} · Skipped: {$summary['skipped']}");
    }

    Log::info('SyncProductsBulkAction summary', $summary);
  }

  /**
   * Shop-Optionen aus config('woo.profiles') lesen.
   *
   * @return array<string,string>
   */
  protected function shopOptions(): array
  {
    $profiles = (array) config('woo.profiles', []);
    if (empty($profiles)) {
      // Fallback: nur aktuelles Default-Profil
      return ['default' => 'Default'];
    }

    // Labels aus Keys ableiten: 'staging' -> 'Staging'
    $opts = [];
    foreach ($profiles as $key => $_) {
      $opts[$key] = ucfirst((string) $key);
    }
    return $opts;
  }

  /**
   * Default-Shop-Key bestimmen.
   */
  protected function defaultShopKey(): string
  {
    $profiles = (array) config('woo.profiles', []);
    if (empty($profiles)) return 'default';
    // bevorzugt 'staging' oder 'production' falls vorhanden
    if (isset($profiles['staging'])) return 'staging';
    if (isset($profiles['production'])) return 'production';
    // sonst erster Key
    return array_key_first($profiles);
  }

  /**
   * Aktiviert ein Profil temporär, indem config(woo.api.*) überschrieben wird.
   * Wenn $key 'default' ist oder unbekannt, passiert nichts.
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
    Log::info('SyncProductsBulkAction: applied shop profile', ['profile' => $key]);
  }

  /**
   * Prüft zur Laufzeit, ob eine Spalte vorhanden ist (z. B. woo_synced_at).
   */
  protected function hasColumn(Product $product, string $column): bool
  {
    return Schema::hasColumn($product->getTable(), $column);
  }
}
