<?php

namespace App\Filament\Resources\ProductResource\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Woo\ProductUpsertService;
use App\Services\Woo\ResolvedTarget;
use App\Services\Woo\WooParentResolver;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SyncProductsBulkAction
 *
 * Filament BulkAction ohne eigenen __construct() (Konstruktor ist in Filament final).
 * Konfiguration erfolgt in setUp().
 *
 * - Bildet pro Produkt einen Kandidaten
 * - Resolved Ziel-IDs in Woo via WooParentResolver
 * - Ruft ProductUpsertService::upsert(int $shopId, array $product, ?int $productId, ?int $variationId)
 * - Zählt eine kleine Summary für Notifications
 *
 * @since 2025-09-25
 */
class SyncProductsBulkAction extends BulkAction
{
  /**
   * Optionaler Default-Name, falls du die Action per Class referenzierst.
   */
  public static function getDefaultName(): ?string
  {
    return 'syncProducts';
  }

  /**
   * Action-Konfiguration (kein eigener Konstruktor!).
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this
      ->label('Produkte synchronisieren')
      ->icon('heroicon-m-arrow-path')
      ->requiresConfirmation()
      ->action(function (Collection $records): void {
        /** @var WooParentResolver $resolver */
        $resolver = app(WooParentResolver::class);
        /** @var ProductUpsertService $upsert */
        $upsert   = app(ProductUpsertService::class);

        $summary = [
          'products' => 0,
          'created'  => 0,
          'updated'  => 0,
          'skipped'  => 0,
          'errors'   => 0,
        ];

        /** @var Product $product */
        foreach ($records as $product) {
          try {
            // Shop ableiten: bevorzugt Relation / shop_id, sonst Fallback first()
            $shop = $product->shop
              ?? (isset($product->shop_id) ? Shop::find($product->shop_id) : null)
              ?? Shop::query()->first();

            if (!$shop) {
              $summary['products']++;
              $summary['skipped']++;
              Log::warning('SyncProductsBulkAction: no shop context', [
                'product_id' => $product->id,
              ]);
              continue;
            }

            // Kandidat aufbauen (nutzt vorhandene Felder, passe bei Bedarf an)
            $candidate = [
              'id'          => $product->id,
              'type'        => $product->product_type ?? 'simple', // 'simple' | 'variable' | 'variation'
              'product_name' => $product->product_name ?? null,
              'sku'         => $product->sku ?? null,
              'ean'         => $product->ean ?? null,
              'mpn'         => $product->mpn ?? null,
              'brand'       => optional($product->brand)->name ?? $product->brand ?? null,
              'attributes'  => array_filter([
                'color' => $product->color ?? optional($product->variation)->color ?? null,
                'size'  => $product->size  ?? optional($product->variation)->size  ?? null,
              ], static fn($v) => $v !== null && $v !== ''),

              // Wenn du eigene Builder hast, kannst du sie hier einhängen.
              // 'payload'        => $this->buildProductPayload($product),
              // 'parent_payload' => $this->buildParentPayloadIfNeeded($product),
            ];

            // Resolve Ziel in Woo
            $resolved = $resolver->resolve($shop, $candidate);
            if (is_array($resolved)) {
              $resolved = ResolvedTarget::fromArray($resolved);
            }

            // Guards & Logging (Variation ohne SKU überspringen)
            $isVariation = ($candidate['type'] ?? 'simple') === 'variation';
            $isParent    = ($candidate['type'] ?? 'simple') === 'variable';

            if ($isVariation && empty($candidate['sku'])) {
              $summary['products']++;
              $summary['skipped']++;
              Log::warning('Skip variation: missing SKU', [
                'shopId'     => $shop->id,
                'product_id' => $product->id,
              ]);
              continue;
            }

            if ($isParent && !empty($candidate['sku'])) {
              Log::warning('Parent has SKU set (should be empty in Woo)', [
                'shopId'     => $shop->id,
                'product_id' => $product->id,
                'sku'        => $candidate['sku'],
              ]);
            }

            if ($resolved->conflict) {
              $summary['products']++;
              $summary['errors']++;
              Log::error('Duplicate identifier, manual review required', [
                'shopId'     => $shop->id,
                'product_id' => $product->id,
                'sku'        => $candidate['sku'] ?? null,
                'match'      => $resolved->match_type,
              ]);
              continue;
            }

            // Upsert (beachte: int $shopId!)
            $upsert->upsert(
              $shop->id,
              $candidate,
              $resolved->woo_product_id,
              $resolved->woo_variation_id
            );

            // Action für Summary ableiten
            $action = 'unknown';
            if ($isVariation) {
              $action = $resolved->woo_variation_id ? 'update_variation' : 'create_variation';
            } else {
              $action = $resolved->woo_product_id ? 'update_product' : 'create_product';
            }

            $summary['products']++;
            if (in_array($action, ['create_product', 'create_variation'], true)) {
              $summary['created']++;
            } elseif (in_array($action, ['update_product', 'update_variation'], true)) {
              $summary['updated']++;
            }

            // Optional: Flag setzen, falls vorhanden
            if ($product->isFillable('woo_synced_at')) {
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

        // Kurzes Feedback in Log (oder Notification)
        Log::info('SyncProductsBulkAction summary', $summary);
      });
  }
}
