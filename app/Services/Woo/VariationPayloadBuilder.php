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
      $payload['stock_status'] = $variation->stock_status;
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
  private function resolveAttributes(Product $parent, ProductVariation $variation): array
  {
    // 1) Resolver verwenden, falls vorhanden
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

    // 2) Fallback: einfache Feldzuordnung
    $map = [
      'pa_color' => ['color', 'farbe', 'colour'],
      'pa_size'  => ['size', 'groesse', 'größe', 'gr'],
    ];

    $attrs = [];
    foreach ($map as $slug => $fields) {
      foreach ($fields as $f) {
        if (isset($variation->{$f}) && $variation->{$f} !== null && $variation->{$f} !== '') {
          $attrs[] = [
            'name'   => $slug,
            'option' => (string) $variation->{$f},
          ];
          break;
        }
      }
    }

    return $attrs;
  }

  public function buildForVariation(\App\Models\Product $parent, \App\Models\ProductVariation $variation): array
  {
    return $this->build($parent, $variation);
  }
}
