<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Services\Woo\ProductUpsertService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncVariationsBulkAction
 *
 * Zweck:
 * - Synchronisiert ausgewählte Varianten mit WooCommerce (SKU-first-Strategie).
 * - Nutzt ProductUpsertService (intern: WooParentResolver + Orchestrator).
 *
 * Formular-Hinweis:
 * - Das Shop-Select liefert die Shop-ID (Anzeige = Shop-Name).
 *
 * Logging:
 * - Ausführliche Logs auf DEBUG/INFO/ERROR.
 */
class SyncVariationsBulkAction
{
  /**
   * Hauptlogik der Action inkl. Notification-Ausgabe.
   *
   * @param  Collection<int,ProductVariation|Product> $records
   * @param  array{shop:int,only_changed?:bool,dry_run?:bool} $data
   */
  protected function handle(Collection $records, array $data): void
  {
    // Shop ermitteln (Form gibt eine ID zurück)
    /** @var Shop|null $shop */
    $shop = Shop::find((int) ($data['shop'] ?? 0));

    if (!$shop) {
      Log::error('SyncVariationsBulkAction: Shop konnte nicht aufgelöst werden.', ['shop_arg' => $data['shop'] ?? null]);
      Notification::make()->title('Woo-Sync abgebrochen')->body('Shop konnte nicht aufgelöst werden. Bitte Auswahl prüfen.')->danger()->send();
      return;
    }

    $summary = ['variations' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($records as $record) {
      // $record kann je nach Selektion ProductVariation ODER Product sein.
      /** @var ProductVariation|Product $record */

      $variation = $record instanceof ProductVariation ? $record : ($record->variation ?? null);
      if (!$variation) {
        // Keine Variation ableitbar → überspringen
        $summary['skipped']++;
        Log::info('SyncVariationsBulkAction: skipped (no variation resolvable)', [
          'record_id' => $record->id ?? null,
          'record_type' => $record instanceof ProductVariation ? 'ProductVariation' : 'Product',
        ]);
        continue;
      }

      // Optional: nur geänderte synchronisieren (wenn Zeitstempel vorhanden)
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

        // Stamm-Produkt bestimmen (für Parent-Payload & Marke)
        /** @var Product|null $product */
        $product = $variation->product ?? ($record instanceof Product ? $record : null);

        $candidate = [
          // SKU-First: Variante hat i.d.R. eigene SKU; sonst auf Produkt/ArtNr. ausweichen
          'sku'   => $variation->sku ?? ($product?->sku ?? $product?->product_number ?? null),
          'ean'   => $variation->ean ?? $product?->ean ?? null,
          'mpn'   => $variation->mpn ?? $product?->mpn ?? null,
          'brand' => $product?->brand->name ?? $product?->brand ?? null,

          // Varianten-Attribute
          'attributes' => array_filter([
            'color' => $variation->color ?? null,
            'size'  => $variation->size ?? null,
          ], fn($v) => $v !== null && $v !== ''),

          // Explizit als Variante kennzeichnen
          'is_variant' => true,

          // Payloads:
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

        // Timestamp setzen, wenn Feld existiert
        if (property_exists($variation, 'woo_synced_at') || \Schema::hasColumn($variation->getTable(), 'woo_synced_at')) {
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
   * Baut den Payload für eine Variation (kein 'type' Feld!).
   *
   * @param  ProductVariation               $variation
   * @param  Product|null                   $product
   * @return array
   */
  protected function buildVariationPayload(ProductVariation $variation, ?Product $product): array
  {
    // Preis: Integer Cents oder Float abdecken
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
      'sku'           => $variation->sku ?: ($product?->sku ?? $product?->product_number ?? null), // wenn Var-SKU fehlt, notfalls Parent
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

    // Null/Leere sauber entfernen
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

    return [
      'name'       => $name,
      'type'       => 'variable',
      'attributes' => $attributes,
    ];
  }
}
