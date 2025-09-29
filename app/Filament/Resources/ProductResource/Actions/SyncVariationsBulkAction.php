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

class SyncVariationsBulkAction extends BulkAction
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->label('Variante synchronisieren')
      ->modalHeading('Variante synchronisieren')
      ->requiresConfirmation()
      ->icon('heroicon-o-arrows-right-left')
      ->color('primary') // Alternativen: secondary|success|warning|danger|gray
      ->form([
        Select::make('shop')
          ->label('Shop')
          ->options(fn() => Shop::query()->orderBy('name')->pluck('name', 'id')->all())
          ->default(fn() => Shop::query()->orderBy('name')->value('id'))
          ->required()
          ->searchable()
          ->native(false)
          ->preload()
          ->helperText('Ziel-Shop auswählen (Anzeige = Name).'),
        Toggle::make('only_changed')
          ->label('Nur geänderte synchronisieren')
          ->default(true)
          ->helperText('Überspringt Datensätze ohne Änderungen seit letztem Sync (benötigt Spalte woo_synced_at).'),
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
   * @param  Collection<int,Product|ProductVariation>  $records
   * @param  array{shop:int,only_changed?:bool,dry_run?:bool} $data
   */
  protected function handle(Collection $records, array $data): void
  {
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

    $variations = $this->expandRecordsToVariations($records);
    Log::info('SyncVariationsBulkAction: expanded selection', [
      'input_count'     => $records->count(),
      'variation_count' => $variations->count(),
    ]);

    if ($variations->isEmpty()) {
      Notification::make()->title('Keine Varianten gefunden')->body('Für die gewählten Produkte wurden keine Varianten ermittelt.')->warning()->send();
      Log::info('SyncVariationsBulkAction: no variations after expand');
      return;
    }

    $summary = ['variations' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($variations as $variation) {
      /** @var ProductVariation $variation */

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

        // Einheitliche Taxonomie-Slugs (konfigurierbar)
        $tax = (array) config('woo.mapping.variation_attribute_taxonomies', [
          'color' => 'pa_color',
          'size'  => 'pa_size',
        ]);
        $taxColor = $tax['color'] ?? 'pa_color';
        $taxSize  = $tax['size']  ?? 'pa_size';

        $candidate = [
          'sku'   => $variation->sku ?? ($product?->sku ?? $product?->product_number ?? null),
          'ean'   => $variation->ean ?? $product?->ean ?? null,
          'mpn'   => $variation->mpn ?? $product?->mpn ?? null,
          'brand' => $product?->brand->name ?? $product?->brand ?? null,

          // Variation-Attribute → exakt dieselben Namen wie am Parent (Taxonomie-Slugs!)
          'attributes' => array_values(array_filter([
            ($variation->color ?? null) ? ['name' => $taxColor, 'option' => (string) $variation->color] : null,
            ($variation->size  ?? null) ? ['name' => $taxSize,  'option' => (string) $variation->size] : null,
          ])),

          'is_variant'     => true,
          'payload'        => $this->buildVariationPayload($variation, $product, $taxColor, $taxSize),
          'parent_payload' => $this->buildParentPayloadWithTaxonomies($product, $taxColor, $taxSize),
        ];

        $res    = $upsert->upsert($shop->id, $candidate);
        $action = $res['action'] ?? 'unknown';

        $summary['variations']++;
        if (in_array($action, ['create_product', 'create_variation'], true)) {
          $summary['created']++;
        } elseif (in_array($action, ['update_product', 'update_variation'], true)) {
          $summary['updated']++;
        }

        if (Schema::hasColumn($variation->getTable(), 'woo_synced_at')) {
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
   * Produkte/Varianten → eindeutige Liste von Variationen
   */
  protected function expandRecordsToVariations(Collection $records): Collection
  {
    $list = collect();

    foreach ($records as $record) {
      if ($record instanceof ProductVariation) {
        $list->push($record);
        continue;
      }

      if ($record instanceof Product) {
        $relation = null;
        foreach (['variations', 'productVariations', 'variants'] as $candidate) {
          if (method_exists($record, $candidate)) {
            $relation = $candidate;
            break;
          }
        }

        if (!$relation) {
          Log::warning('SyncVariationsBulkAction: Produkt hat keine erkennbare Varianten-Relation', ['product_id' => $record->id]);
          continue;
        }

        $record->loadMissing($relation);
        foreach ($record->{$relation} as $var) {
          if ($var instanceof ProductVariation) {
            $list->push($var);
          }
        }
      }
    }

    return $list->filter()->unique(fn(ProductVariation $v) => $v->id)->values();
  }

  /**
   * Variation-Payload: setzt Attribute-Namen auf die Taxonomie-Slugs (z. B. pa_color, pa_size)
   */
  protected function buildVariationPayload(ProductVariation $variation, ?Product $product, string $taxColor, string $taxSize): array
  {
    $price = null;
    if (isset($variation->price) && $variation->price !== null) {
      $price = is_numeric($variation->price) ? number_format((float) $variation->price, 2, '.', '') : null;
    } elseif (isset($variation->price_cents) && $variation->price_cents !== null) {
      $price = number_format(((int) $variation->price_cents) / 100, 2, '.', '');
    }

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
        ($variation->color ?? null) ? ['name' => $taxColor, 'option' => (string) $variation->color] : null,
        ($variation->size  ?? null) ? ['name' => $taxSize,  'option' => (string) $variation->size] : null,
      ])),
    ];

    Log::debug('SyncVariationsBulkAction: built variation payload', ['variation_id' => $variation->id]);

    return array_filter($payload, fn($v) => $v !== null && $v !== []);
  }

  /**
   * Parent-Payload: legt variable Attribute mit denselben Taxonomie-Slugs an,
   * damit Variations-Attribute exakt passen.
   */
  protected function buildParentPayloadWithTaxonomies(?Product $product, string $taxColor, string $taxSize): array
  {
    $name = $product?->product_name ?? $product?->name ?? 'Variable Product';

    $attributes = array_values(array_filter([
      ['name' => $taxColor, 'visible' => true, 'variation' => true, 'options' => []],
      ['name' => $taxSize,  'visible' => true, 'variation' => true, 'options' => []],
    ], fn($a) => !empty($a['name'])));

    $parent = [
      'name'       => $name,
      'type'       => 'variable',
      'attributes' => $attributes,
    ];

    Log::debug('SyncVariationsBulkAction: built parent payload (taxonomy)', [
      'product_id' => $product->id ?? null,
      'attributes' => array_column($attributes, 'name'),
    ]);

    return $parent;
  }
}
