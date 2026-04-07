<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Support\ProductNameNormalizer;

/**
 * Resolves display names for product variations (child products).
 *
 * Strategy:
 * - Build the parent product name via DefaultProductNameBuilder.
 * - Analyze all variation properties of the parent product.
 * - Detect one global lead property that is shared across all variations.
 * - Append only variation-specific properties to the child name.
 */
final class VariationDisplayNameResolver
{
    public function __construct(
        private readonly DefaultProductNameBuilder $builder,
        private readonly ParentLeadPropertyResolver $parentLeadPropertyResolver,
    ) {}

    /**
     * Builds the display name for a product variation (child product).
     *
     * Strategy:
     * 1. Build the parent product name via DefaultProductNameBuilder
     *    (includes manufacturer, category, designation and parent lead property).
     * 2. Analyze all sibling variations of the same parent product.
     * 3. Detect the global parent lead property (shared across all variations).
     * 4. Append only variation-specific properties to the parent name.
     *
     * Example:
     * - Parent:
     *   "ALIENS - Karabiner - Stahlkarabiner Total Oval Trilock Mit Pin - Trilock"
     * - Child:
     *   "ALIENS - Karabiner - Stahlkarabiner Total Oval Trilock Mit Pin - Trilock - Schwarz"
     */
    public function resolve(ProductVariation $variation): string
    {
        $variation->loadMissing([
            'product.manufacturer',
            'product.variations.attributeValues.attribute',
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

        $analysis = $this->analyzeProductVariationProperties($product);
        $parentLead = $this->parentLeadPropertyResolver->resolve(
            $parentCtx->categoryName,
            $parentCtx->designation,
            $analysis['global'],
        );

        $properties = $this->resolveVariationProperties($variation);

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
     * Analyzes all variation properties of a parent product.
     *
     * Returns two groups:
     * - global: attribute types that have exactly one identical value across all variations
     * - variable: attribute types that differ between variations
     *
     * @return array{
     *   global: array<string, array<int, string>>,
     *   variable: array<string, array<int, string>>
     * }
     */
    private function analyzeProductVariationProperties(Product $product): array
    {
        $groups = [];

        $product->loadMissing('variations.attributeValues.attribute');

        foreach ($product->variations as $variation) {
            $propertiesByType = $this->resolveVariationPropertiesByType($variation);

            foreach ($propertiesByType as $type => $value) {
                if ($value === '') {
                    continue;
                }

                $groups[$type][$value] = true;
            }
        }

        $global = [];
        $variable = [];

        foreach ($groups as $type => $set) {
            $values = array_keys($set);
            sort($values, SORT_NATURAL | SORT_FLAG_CASE);

            if (count($values) === 1) {
                $global[$type] = $values;
            } else {
                $variable[$type] = $values;
            }
        }

        return [
            'global' => $global,
            'variable' => $variable,
        ];
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
        $propertiesByType = $this->resolveVariationPropertiesByType($variation);

        return array_values(array_filter([
            $propertiesByType['durchmesser'] ?? null,
            $propertiesByType['groesse'] ?? null,
            $propertiesByType['version'] ?? null,
            $propertiesByType['farbe'] ?? null,
            $propertiesByType['laenge'] ?? null,
        ]));
    }

    /**
     * Resolves variation properties keyed by normalized attribute type.
     *
     * Data sources:
     * - preferred: relational attribute pivot
     * - additional: attributes_json fallback/merge
     *
     * If both sources provide the same type, pivot wins.
     *
     * @return array<string, string>
     */
    private function resolveVariationPropertiesByType(ProductVariation $variation): array
    {
        $properties = [];

        // 1) Pivot first
        if ($variation->relationLoaded('attributeValues')) {
            foreach ($variation->attributeValues as $attributeValue) {
                $type = $this->normalizeVariableAttributeType(
                    (string) ($attributeValue->attribute?->name ?? '')
                );

                $value = $this->normalizeNamingValue($attributeValue->value);

                if ($type === null || $value === '') {
                    continue;
                }

                $properties[$type] = $value;
            }
        }

        // 2) JSON ergänzend
        $json = $variation->attributes_json;

        if (! is_array($json)) {
            return $properties;
        }

        foreach ($json as $rawKey => $rawValue) {
            $type = $this->normalizeVariableAttributeType((string) $rawKey);
            $value = $this->normalizeNamingValue($rawValue);

            if ($type === null || $value === '') {
                continue;
            }

            // Pivot hat Vorrang, JSON füllt nur Lücken
            if (! isset($properties[$type])) {
                $properties[$type] = $value;
            }
        }

        return $properties;
    }

    /**
     * Normalizes an attribute name into a generic attribute type.
     *
     * This is used to group variation attributes from different manufacturers
     * into consistent naming buckets such as:
     * - durchmesser
     * - groesse
     * - version
     * - laenge
     * - farbe
     *
     * Returns null if the attribute is not relevant for naming.
     */
    private function normalizeVariableAttributeType(string $name): ?string
    {
        $value = mb_strtolower(trim($name));

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/^attribute\s+group:\s*/iu', '', $value) ?? $value;
        $value = trim($value);

        if (preg_match('/durchmesser|diameter/u', $value)) {
            return 'durchmesser';
        }

        if (preg_match('/größe|groesse|size/u', $value)) {
            return 'groesse';
        }

        if (preg_match('/version|verschluss|schnapper|karabinerverschlu/u', $value)) {
            return 'version';
        }

        if (preg_match('/seillänge|seillaenge|länge|laenge|length/u', $value)) {
            return 'laenge';
        }

        if (preg_match('/seilfarbe|karabinerfarbe|farbe|color/u', $value)) {
            return 'farbe';
        }

        return null;
    }

    /**
     * Normalisiert einen Attributwert für das Naming.
     *
     * @param mixed $value
     * @return string
     */
    private function normalizeNamingValue(mixed $value): string
    {
        return ProductNameNormalizer::normalizeAttributeValue(
            is_scalar($value) ? (string) $value : null
        );
    }
}
