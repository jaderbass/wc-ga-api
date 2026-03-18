<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\ProductNaming\ProductKind;

final class VariationDisplayNameResolver
{
    public function __construct(
        private readonly DefaultProductNameBuilder $builder,
    ) {}

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

        $ctx = new ProductNameContext(
            kind: ProductKind::Variable,
            manufacturerName: (string) ($product->manufacturer->manufacturer ?? ''),
            categoryName: $this->resolveCategoryName($product),
            designation: (string) ($product->original_product_name ?? ''),
            properties: $this->resolveVariationProperties($variation),
            manufacturerId: (int) $product->manufacturer_id,
        );

        return $this->builder->build($ctx)->productName ?: (string) ($variation->sku ?? '—');
    }

    /**
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

    private function resolveCategoryName(Product $product): string
    {
        $designation = trim((string) ($product->original_product_name ?? ''));

        if ($designation === '') {
            $designation = trim((string) ($product->slug ?? ''));
        }

        return ProductNameContext::resolveCategoryName($designation);
    }
}
