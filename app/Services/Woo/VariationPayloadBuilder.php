<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Log;

class VariationPayloadBuilder
{
  /**
   * Haupt-Einstieg: baut das Woo-Payload für eine einzelne Variante.
   * - nutzt WooAttributeResolver (falls vorhanden), sonst Fallback-Mapping
   * - keine Preisfelder
   */
  public function build(Product $parent, ProductVariation $variation): array
  {
    $attributes = $this->resolveAttributes($parent, $variation);

    if (empty($attributes)) {
      Log::warning('VariationPayloadBuilder: no attributes for variation', [
        'variation_id' => $variation->id,
        'sku'          => $variation->sku,
        'product_id'   => $parent->id,
      ]);
    }

    $payload = [
      'sku'        => $variation->sku ?: null,
      'attributes' => array_values($attributes),
    ];

    // Lager / Bestand
    if ($variation->manage_stock) {
      $payload['manage_stock'] = true;
      if (property_exists($variation, 'stock_quantity') && $variation->stock_quantity !== null) {
        $payload['stock_quantity'] = (int) $variation->stock_quantity;
      }
    }

    if (!empty($variation->stock_status)) {
      $mapped = $this->mapStockStatus($variation->stock_status);
      if ($mapped !== null) {
        $payload['stock_status'] = $mapped; // 'instock'|'outofstock'|'onbackorder'
      }
    }

    if (!empty($variation->backorders)) {
      $payload['backorders'] = $variation->backorders;
    }


    // Dimensionen und Gewicht
    $dims = [
      'weight' => $variation->weight_g ? (string) ($variation->weight_g / 1000) : null,
      'length' => $variation->length_mm ? (string) ($variation->length_mm / 10) : null,
      'width'  => $variation->width_mm  ? (string) ($variation->width_mm / 10) : null,
      'height' => $variation->height_mm ? (string) ($variation->height_mm / 10) : null,
    ];
    $dims = array_filter($dims, fn($v) => $v !== null && $v !== '');
    if (!empty($dims)) {
      $payload['dimensions'] = $dims;
    }

    Log::debug('VariationPayloadBuilder: single payload built', [
      'product_id'   => $parent->id,
      'variation_id' => $variation->id,
      'sku'          => $variation->sku,
      'attributes'   => $payload['attributes'],
    ]);

    return $payload;
  }

  /**
   * Liefert die Woo-Attribute einer Variante.
   * - bevorzugt WooAttributeResolver, sonst Fallback-Feldmapping
   */
  private function resolveAttributes(\App\Models\Product $parent, \App\Models\ProductVariation $variation): array
  {
    // 1) Resolver bevorzugen, wenn vorhanden
    if (app()->bound(WooAttributeResolver::class)) {
      try {
        $resolver = app(WooAttributeResolver::class);
        foreach (['attributesForVariation', 'buildVariationAttributes', 'resolveVariationAttributes'] as $method) {
          if (method_exists($resolver, $method)) {
            $attrs = $resolver->{$method}($parent, $variation);
            if (is_array($attrs) && !empty($attrs)) {
              return array_values($attrs);
            }
          }
        }
      } catch (\Throwable $e) {
        Log::warning('WooAttributeResolver failed for variation', [
          'variation_id' => $variation->id,
          'error'        => $e->getMessage(),
        ]);
      }
    }

    // 2) DB-basierter Fallback über Pivot:
    // piv (product_variation_attribute_value) -> pav (product_attribute_values) -> pa (product_attributes)
    $rows = \Illuminate\Support\Facades\DB::table('product_variation_attribute_value as piv')
      ->join('product_attribute_values as pav', 'pav.id', '=', 'piv.product_attribute_value_id')
      ->join('product_attributes as pa', 'pa.id', '=', 'pav.attribute_id')
      ->where('piv.product_variation_id', $variation->id)
      ->select([
        'pa.slug as attr_slug',         // z. B. 'pa_size', 'pa_color' (oder projekt-spezifische Slugs)
        'pav.value as option_value',    // sichtbarer Optionswert (z. B. 'M', 'Blau')
      ])
      ->get();

    $attrs = [];
    foreach ($rows as $r) {
      $slug = (string) ($r->attr_slug ?? '');
      $val  = (string) ($r->option_value ?? '');
      if ($slug === '' || $val === '') {
        continue;
      }
      $attrs[] = [
        'name'   => $slug,
        'option' => $val,
      ];
    }

    return $attrs;
  }

  /**
   * (optional) Mappt lokale Stock-Status-Werte auf Woo-REST-kompatible Werte.
   * Aufruf: beim Payload-Bau vor dem Setzen von 'stock_status' verwenden.
   */
  private function mapStockStatus(?string $status): ?string
  {
    if ($status === null || $status === '') return null;

    $s = strtolower(str_replace([' ', '-'], '_', $status));
    return match ($s) {
      'in_stock', 'instock'         => 'instock',
      'out_of_stock', 'outofstock'  => 'outofstock',
      'on_backorder', 'backorder'   => 'onbackorder',
      default                       => null, // Unbekannt → nicht senden
    };
  }


  public function buildForVariation(\App\Models\Product $parent, \App\Models\ProductVariation $variation): array
  {
    // Alias für ältere Aufrufer – delegiert auf build()
    return $this->build($parent, $variation);
  }
}
