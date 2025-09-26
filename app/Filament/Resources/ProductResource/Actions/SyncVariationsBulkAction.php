<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Services\Woo\ProductUpsertService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Class SyncVariationsBulkAction
 *
 * Zweck:
 * - Synchronisiert ausgewählte Varianten mit WooCommerce (SKU-first-Strategie).
 * - Verwendet ProductUpsertService (intern: WooParentResolver + Orchestrator).
 *
 * Formular:
 * - Shop-Auswahl zeigt den Shop-Namen an, übergibt aber die Shop-ID.
 * - Optional: Nur geänderte Datensätze / Dry-Run.
 *
 * Logging:
 * - Ausführliche Logs auf DEBUG/INFO/ERROR.
 */
class SyncVariationsBulkAction extends BulkAction
{
  /**
   * Setzt Label, Icon, Formular und die Action-Logik.
   *
   * @return void
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Varianten synchronisieren')
      ->icon('heroicon-o-arrows-right-left')
      ->modalHeading('Varianten synchronisieren')
      ->requiresConfirmation()
      ->color('primary')
      ->form([
        Select::make('shop')
          ->label('Shop')
          ->options(
            // key = id, value = name → Anzeige "Staging", Wert ist ID
            fn() => Shop::query()->orderBy('name')->pluck('name', 'id')->all()
          )
          ->default(fn() => Shop::query()->orderBy('name')->value('id')) // erster Shop als Default
          ->required()
          // --- UI-Fix: Tom Select aktivieren ---
          ->searchable()     // macht aus native <select> → Tom Select
          ->native(false)    // erzwingt JS-Select; unser CSS greift
          ->preload()        // lädt Optionen sofort (bessere UX)
          ->helperText('Ziel-Profil (definierbar unter woo.profiles in config/woo.php).'),
        Toggle::make('only_changed')
          ->label('Nur geänderte synchronisieren')
          ->default(true)
          ->helperText('Überspringt Varianten ohne Änderungen seit letztem Sync (benötigt Spalte woo_synced_at).'),
        Toggle::make('dry_run')
          ->label('Dry-Run (ohne Schreiben)')
          ->default(false)
        ->helperText('Kein Versand an Woo; nur Vorschau im Log.'),
      ])
      ->action(function (Collection $records, array $data): void {
        $this->handle($records, $data);
      });
  }

  /**
   * Hauptlogik der Action inkl. Notification-Ausgabe.
   *
   * @param  Collection<int,ProductVariation|Product> $records
   * @param  array{shop:int,only_changed?:bool,dry_run?:bool} $data
   * @return void
   */
  protected function handle(Collection $records, array $data): void
  {
    // Falls du eine Profil-Umschaltung nutzt, hier optional anwenden:
    if (method_exists($this, 'applyShopProfile')) {
      $this->applyShopProfile($data['shop'] ?? null);
    }

    /** @var Shop|null $shop */
    $shop = Shop::find((int) ($data['shop'] ?? 0));

    if (!$shop) {
      Log::error('SyncVariationsBulkAction: Shop konnte nicht aufgelöst werden.', ['shop_arg' => $data['shop'] ?? null]);
      Notification::make()->title('Woo-Sync abgebrochen')->body('Shop konnte nicht aufgelöst werden. Bitte Auswahl prüfen.')->danger()->send();
      return;
    }

    $summary = ['variations' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($records as $record) {
      /** @var ProductVariation|Product $record */
      $variation = $record instanceof ProductVariation ? $record : ($record->variation ?? null);

      if (!$variation) {
        $summary['skipped']++;
        Log::info('SyncVariationsBulkAction: skipped (no variation resolvable)', [
          'record_id'   => $record->id ?? null,
          'record_type' => $record instanceof ProductVariation ? 'ProductVariation' : 'Product',
        ]);
        continue;
      }

      // Nur geänderte?
      if (!empty($data['only_changed'])) {
        $last = $variation->woo_synced_at ?? null;
        if ($last && $variation->updated_at && $variation->updated_at->lte($last)) {
          $summary['skipped']++;
          Log::info('SyncVariationsBulkAction: skipped (no changes since last sync)', [
            'variation_id' => $variation->id,
            'updated_at'   => (string) $variation->updated_at,
            'woo_synced_at' => (string) $last,
          ]);
          continue;
        }
      }

      if (!empty($data['dry_run'])) {
        Log::info('SyncVariationsBulkAction (dry-run): variation preview', [
          'variation_id' => $variation->id,
          'sku'          => $variation->sku ?? null,
          'color'        => $variation->color ?? null,
          'size'         => $variation->size ?? null,
        ]);
        $summary['variations']++;
        continue;
      }

      try {
        /** @var ProductUpsertService $upsert */
        $upsert = app(ProductUpsertService::class);

        /** @var Product|null $product */
        $product = $variation->product ?? null;

        $candidate = [
          'sku'   => $variation->sku ?? ($product?->sku ?? $product?->product_number ?? null),
          'ean'   => $variation->ean ?? $product?->ean ?? null,
          'mpn'   => $variation->mpn ?? $product?->mpn ?? null,
          'brand' => $product?->brand->name ?? $product?->brand ?? null,

          'attributes' => array_filter([
            'color' => $variation->color ?? null,
            'size'  => $variation->size ?? null,
          ], fn($v) => $v !== null && $v !== ''),

          'is_variant'    => true,
          'payload'        => $this->buildVariationPayload($variation, $product),
          'parent_payload' => $this->buildParentPayloadIfNeeded($product),
        ];

        $res    = $upsert->upsert($shop, $candidate);
        $action = $res['action'] ?? 'unknown';

        $summary['variations']++;
        if (in_array($action, ['create_product', 'create_variation'], true)) {
          $summary['created']++;
        } elseif (in_array($action, ['update_product', 'update_variation'], true)) {
          $summary['updated']++;
        }

        // Timestamp nach erfolgreichem Sync
        if (property_exists($variation, 'woo_synced_at') || Schema::hasColumn($variation->getTable(), 'woo_synced_at')) {
          $variation->forceFill(['woo_synced_at' => now()])->saveQuietly();
        }
      } catch (\Throwable $e) {
        $summary['variations']++;
        $summary['errors']++;
        Log::error('SyncVariationsBulkAction exception', [
          'variation_id' => $variation->id ?? null,
          'error'        => $e->getMessage(),
        ]);
      }
    }

    $title = !empty($data['dry_run']) ? 'Dry-run abgeschlossen' : 'Woo-Varianten-Sync abgeschlossen';
    $body  = sprintf(
      "Gesamt: %d\nErstellt: %d · Aktualisiert: %d · Übersprungen: %d · Fehler: %d",
      $summary['variations'],
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

    Log::info('SyncVariationsBulkAction summary', $summary);
  }

  /**
   * Baut den Payload für eine Variation (kein 'type'-Feld).
   *
   * @param  ProductVariation $variation
   * @param  Product|null     $product
   * @return array
   */
  protected function buildVariationPayload(ProductVariation $variation, ?Product $product): array
  {
    $price = null;
    if (isset($variation->price) && $variation->price !== null) {
      $price = is_numeric($variation->price) ? number_format((float) $variation->price, 2, '.', '') : null;
    } elseif (isset($variation->price_cents) && $variation->price_cents !== null) {
      $price = number_format(((int) $variation->price_cents) / 100, 2, '.', '');
    }

    $attrMap  = (array) config('woo.mapping.variation_attribute_map', []);
    $wooSize  = $attrMap['size']  ?? 'Size';
    $wooColor = $attrMap['color'] ?? 'Color';

    $eanKey = (string) config('woo.mapping.meta_keys.ean', 'ean');
    $mpnKey = (string) config('woo.mapping.meta_keys.mpn', 'mpn');

    $payload = [
      'sku'           => $variation->sku ?: ($product?->sku ?? $product?->product_number ?? null),
      'regular_price' => $price,
      'meta_data'     => array_values(array_filter([
        ($variation->ean ?? $product?->ean ?? null) ? ['key' => $eanKey, 'value' => (string) ($variation->ean ?? $product?->ean)] : null,
        ($variation->mpn ?? $product?->mpn ?? null) ? ['key' => $mpnKey, 'value' => (string) ($variation->mpn ?? $product?->mpn)] : null,
      ])),
      'attributes'    => array_values(array_filter([
        ($variation->color ?? null) ? ['name' => $wooColor, 'option' => (string) $variation->color] : null,
        ($variation->size  ?? null) ? ['name' => $wooSize,  'option' => (string) $variation->size] : null,
      ])),
    ];

    Log::debug('SyncVariationsBulkAction: built variation payload', [
      'variation_id' => $variation->id,
    ]);

    return array_filter($payload, fn($v) => $v !== null && $v !== []);
  }

  /**
   * Parent-Payload nur, wenn Parent noch fehlt und ein variables Produkt angelegt werden soll.
   *
   * @param  Product|null $product
   * @return array|null
   */
  protected function buildParentPayloadIfNeeded(?Product $product): ?array
  {
    if (!$product) {
      return null;
    }

    $isVariable = $product->product_type === 'variable' || $product->variations()->exists();
    if (!$isVariable) {
      return null;
    }

    $name    = $product->product_name ?? $product->name ?? ('Product #' . $product->id);

    $attrMap = (array) config('woo.mapping.variation_attribute_map', []);
    $wooSize  = $attrMap['size']  ?? 'Size';
    $wooColor = $attrMap['color'] ?? 'Color';

    $attributes = array_values(array_filter([
      ['name' => $wooColor, 'visible' => true, 'variation' => true, 'options' => []],
      ['name' => $wooSize,  'visible' => true, 'variation' => true, 'options' => []],
    ], fn($a) => !empty($a['name'])));

    $parent = [
      'name'       => $name,
      'type'       => 'variable',
      'attributes' => $attributes,
    ];

    Log::debug('SyncVariationsBulkAction: built parent payload', [
      'product_id' => $product->id,
      'attributes' => array_column($attributes, 'name'),
    ]);

    return $parent;
  }
}
