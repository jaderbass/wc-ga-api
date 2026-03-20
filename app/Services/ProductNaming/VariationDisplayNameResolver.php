<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use App\Models\ProductVariation;

final class VariationDisplayNameResolver
{
    public function __construct(
        private readonly DefaultProductNameBuilder $builder,
    ) {}

    /**
     * Builds the display name for a product variation (child product).
     *
     * Strategy:
     * 1. Build the parent product name via DefaultProductNameBuilder
     *    (includes manufacturer, category, designation and parent lead property).
     * 2. Extract variation-specific properties (e.g. color, length).
     * 3. Remove the parent lead property if it appears again in the variation.
     * 4. Append remaining variation properties to the parent name.
     *
     * Result:
     * - Parent:
     *   "TEUFELBERGER - Seile - Statikseil 11.0 Patron - 11mm"
     *
     * - Child:
     *   "TEUFELBERGER - Seile - Statikseil 11.0 Patron - 11mm - Weiß/Rot - 30m"
     *
     * Notes:
     * - Variations do NOT use ProductKind::Variable naming directly,
     *   because the builder would only allow one property.
     * - Instead, we reuse the parent name and extend it.
     * - Works with both pivot-based attributes and attributes_json fallback.
     */
    public function resolve(ProductVariation $variation): string
    {
        $variation->loadMissing([
            'product.manufacturer',
            'attributeValues.attribute',
        ]);

        $product = $variation->product;

        if (! $product instanceof Product) {
            return (string) ($variation->sku ?? '—');
        }

        $parentCtx = ProductNameContext::fromProduct($product);
        $parentName = $this->builder->build($parentCtx)->productName;

        if ($parentName === '') {
            return (string) ($variation->sku ?? '—');
        }

        $properties = $this->resolveVariationProperties($variation);
        $parentLead = $parentCtx->properties[0] ?? null;

        $properties = array_values(array_filter(
            $properties,
            static fn(string $value): bool => $value !== '' && $value !== $parentLead
        ));

        if ($properties === []) {
            return $parentName;
        }

        return $parentName . ' - ' . implode(' - ', $properties);
    }

    /**
     * Resolves the ordered list of variation-specific properties.
     *
     * Preferred source is the relational attribute pivot. If no relational
     * attributes are present, the method falls back to attributes_json.
     *
     * @return array<int, string>
     */
    private function resolveVariationProperties(ProductVariation $variation): array
    {
        $properties = [];

        if ($variation->relationLoaded('attributeValues')) {
            foreach ($variation->attributeValues as $attributeValue) {
                $value = trim((string) ($attributeValue->value ?? ''));

                if ($value !== '') {
                    $properties[] = $value;
                }
            }
        }

        if ($properties !== []) {
            return array_values(array_unique($properties));
        }

        $json = $variation->attributes_json;

        if (! is_array($json)) {
            return [];
        }

        foreach ($json as $rawValue) {
            $value = trim((string) $rawValue);

            if ($value !== '') {
                $properties[] = $value;
            }
        }

        return array_values(array_unique($properties));
    }
}
