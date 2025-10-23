<?php

namespace App\Support\Woo;

use App\Models\Product;
use App\Models\ProductVariation;

/**
 * Class PayloadBuilder
 *
 * Baut Woo-konforme Payloads aus Product/Variation anhand von
 * ManufacturerMap, Transformers und WritePolicy.
 */
class PayloadBuilder
{
    /**
     * Erzeuge Payload für ein Woo-Produkt (ohne Variationen).
     * Baut die Woo-Payload (Strings für Preise, kg/cm etc.).
     * Kompatibilität:
     * - Wenn config('woo_policy.compat.wp_all_export_woo_addon') = true und type = simple,
     *   werden 'attributes' und 'default_attributes' entfernt.
     *
     * @param Product $product
     * @return array<string,mixed>
     */
    public function buildProductPayload(Product $product): array
    {
        // TODO: ManufacturerMap + WritePolicy + Transformers einbeziehen.
        return [
            'name' => $product->title ?? $product->product_name ?? 'Produkt',
            'type' => $product->has_variations ? 'variable' : 'simple',
            'sku'  => $product->sku ?? $product->product_number,
            // 'regular_price' => number_format($product->price / 100, 2, '.', ''),
            'description' => $product->description ?? '',
            // Weitere Felder gemäß Mapping ...
        ];
    }

    /**
     * Erzeuge Payload für eine Woo-Variation.
     *
     * @param Product $product
     * @param ProductVariation $variation
     * @return array<string,mixed>
     */
    public function buildVariationPayload(Product $product, ProductVariation $variation): array
    {
        return [
            'sku' => $variation->sku,
            // 'regular_price' => number_format($variation->price / 100, 2, '.', ''),
            'attributes' => [
                // Beispiel: ['name' => 'Size', 'option' => $variation->size]
            ],
        ];
    }
}
