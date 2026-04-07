<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use App\Support\ProductNameNormalizer;

/**
 * Extracts customer-relevant product properties (Eigenschaften)
 * from stored product attribute values.
 *
 * Responsibility:
 * - Reads attribute values from DB models
 * - Maps them into a fixed, ordered list
 * - Does NOT build names
 * - Does NOT format final product names
 *
 * For variable parent products:
 * - Only one global lead property may be returned
 * - Global means: same normalized attribute value across all variations
 * - If no global property exists, category-specific fallback rules may apply
 *   (e.g. rope diameter from designation)
 */
final class ProductPropertyExtractor
{
    public function __construct(
        private readonly ParentLeadPropertyResolver $parentLeadPropertyResolver,
    ) {}

    /**
     * Builds the ordered property list for product naming.
     *
     * @return array<int, string|null> [p1, p2, p3]
     */
    public function extract(Product $product): array
    {
        if ($product->product_type === 'variable') {
            $analysis = $this->analyzeVariablePropertyGroups($product);
            $designation = $this->resolveDesignation($product);
            $categoryName = ProductNameContext::resolveCategoryName($designation);

            $value = $this->parentLeadPropertyResolver->resolve(
                $categoryName,
                $designation,
                $analysis['global'],
            );

            return $value !== null ? [$value] : [];
        }

        $values = $this->collectAttributeValues($product);

        return [
            $values['p1'] ?? null,
            $values['p2'] ?? null,
            $values['p3'] ?? null,
        ];
    }

    /**
     * Analyzes grouped variable properties across all variations.
     *
     * Returns:
     * - global: attribute types with exactly one identical value across all variations
     * - variable: attribute types with multiple values across variations
     *
     * @return array{
     *   global: array<string, array<int, string>>,
     *   variable: array<string, array<int, string>>
     * }
     */
    private function analyzeVariablePropertyGroups(Product $product): array
    {
        $groups = $this->collectVariablePropertyGroups($product);

        $global = [];
        $variable = [];

        foreach ($groups as $type => $values) {
            $uniqueValues = array_values(array_unique(array_map('trim', $values)));
            sort($uniqueValues, SORT_NATURAL | SORT_FLAG_CASE);

            if (count($uniqueValues) === 1) {
                $global[$type] = $uniqueValues;
            } else {
                $variable[$type] = $uniqueValues;
            }
        }

        return [
            'global' => $global,
            'variable' => $variable,
        ];
    }

    /**
     * Collects grouped variable properties from all variations.
     *
     * Example result:
     * [
     *   'durchmesser' => ['11mm'],
     *   'farbe' => ['Weiß/Rot', 'Schwarz'],
     *   'laenge' => ['30 Meter', '40 Meter'],
     *   'version' => ['Trilock'],
     * ]
     *
     * Data sources:
     * - preferred: pivot-based attribute values
     * - additional: attributes_json on variations
     *
     * Both sources are merged to support mixed legacy/import states.
     *
     * @return array<string, array<int, string>>
     */
    private function collectVariablePropertyGroups(Product $product): array
    {
        $product->loadMissing('variations.attributeValues.attribute');

        /** @var array<string, array<string, true>> $groups */
        $groups = [];

        foreach ($product->variations as $variation) {
            // 1) Pivot-based attribute values
            foreach ($variation->attributeValues as $attrValue) {
                $attrName = trim((string) ($attrValue->attribute?->name ?? ''));
                $value = $this->normalizeNamingValue($attrValue->value);

                if ($attrName === '' || $value === '') {
                    continue;
                }

                $type = $this->normalizeVariableAttributeType($attrName);

                if ($type === null) {
                    continue;
                }

                $groups[$type][$value] = true;
            }

            // 2) attributes_json zusätzlich berücksichtigen
            $json = $variation->attributes_json ?? null;

            if (! is_array($json) || $json === []) {
                continue;
            }

            foreach ($json as $key => $rawValue) {
                $attrName = trim((string) $key);
                $value = $this->normalizeNamingValue($rawValue);

                if ($attrName === '' || $value === '') {
                    continue;
                }

                $type = $this->normalizeVariableAttributeType($attrName);

                if ($type === null) {
                    continue;
                }

                $groups[$type][$value] = true;
            }
        }

        $result = [];

        foreach ($groups as $type => $set) {
            $values = array_keys($set);
            sort($values, SORT_NATURAL | SORT_FLAG_CASE);
            $result[$type] = array_values($values);
        }

        return $result;
    }

    /**
     * Collects naming properties (p1..p3) from either:
     * - variation attributeValues (pivot), or
     * - variation attributes_json (Aliens)
     *
     * @return array<string, string> keys: p1|p2|p3
     */
    private function collectAttributeValues(Product $product): array
    {
        $map = [];

        $product->loadMissing('variations.attributeValues.attribute');

        // 1) Preferred: pivot-based attribute values
        foreach ($product->variations as $variation) {
            foreach ($variation->attributeValues as $value) {
                $attrSlug = $value->attribute->slug ?? null;
                if (! is_string($attrSlug) || $attrSlug === '') {
                    continue;
                }

                $normalized = $this->normalizeNamingValue($value->value);
                if ($normalized === '') {
                    continue;
                }

                $candidate = $this->candidateFromSlug($attrSlug, $normalized);
                if ($candidate === null) {
                    continue;
                }

                $this->pushCandidate($map, $candidate['priority'], $candidate['value']);
            }
        }

        if ($map !== []) {
            return $this->toSlots($map);
        }

        // 2) Fallback: attributes_json on variation (Aliens etc.)
        foreach ($product->variations as $variation) {
            $json = $variation->attributes_json ?? null;
            if (! is_array($json) || $json === []) {
                continue;
            }

            foreach ($json as $k => $v) {
                if (! is_string($k)) {
                    continue;
                }

                $val = $this->normalizeNamingValue($v);
                if ($val === null || $val === '') {
                    continue;
                }

                $candidate = $this->candidateFromAliensKey(trim($k), $val);
                if ($candidate === null) {
                    continue;
                }

                $this->pushCandidate($map, $candidate['priority'], $candidate['value']);
            }
        }

        return $this->toSlots($map);
    }

    /**
     * Resolves the designation (base product name) for naming.
     *
     * Priority:
     * - Uses original_product_name if present (preferred source from importer)
     * - Falls back to slug if the original name is missing
     *
     * This ensures that the naming builder always receives a clean,
     * non-generated base designation.
     */
    private function resolveDesignation(Product $product): string
    {
        return (is_string($product->original_product_name) && trim($product->original_product_name) !== '')
            ? trim($product->original_product_name)
            : (string) $product->slug;
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
     * The matcher is intentionally tolerant and also supports compound labels
     * like "Seillänge", "Seilfarbe" or "Karabinerverschluß" as well as source
     * prefixes like "Attribute Group: ...".
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

        if (preg_match('/version|verschluss|verschluß|schnapper|karabinerverschlu/u', $value)) {
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
     * Turns a map of priority => value into p1..p3 slots.
     *
     * @param array<int, string> $byPriority
     * @return array<string, string>
     */
    private function toSlots(array $byPriority): array
    {
        if ($byPriority === []) {
            return [];
        }

        ksort($byPriority);

        $values = array_values($byPriority);
        $values = array_slice($values, 0, 3);

        $slots = [];
        foreach ($values as $i => $v) {
            $slots['p' . ($i + 1)] = $v;
        }

        return $slots;
    }

    /**
     * Adds a candidate if the given priority is still free.
     *
     * @param array<int, string> $byPriority
     */
    private function pushCandidate(array &$byPriority, int $priority, string $value): void
    {
        if ($value === '') {
            return;
        }

        if (! isset($byPriority[$priority])) {
            $byPriority[$priority] = $value;
        }
    }

    /**
     * Maps a generic attribute slug to a naming candidate.
     *
     * @return array{priority:int, value:string}|null
     */
    private function candidateFromSlug(string $slug, string $value): ?array
    {
        $s = mb_strtolower(trim($slug));

        if (preg_match('/\b(size|groesse|größe)\b/u', $s)) {
            return ['priority' => 10, 'value' => $value];
        }

        if (preg_match('/\b(version)\b/u', $s)) {
            return ['priority' => 20, 'value' => $value];
        }

        if (preg_match('/\b(length|laenge|länge)\b/u', $s)) {
            return ['priority' => 30, 'value' => $value];
        }

        if (preg_match('/\b(color|farbe)\b/u', $s)) {
            return ['priority' => 40, 'value' => $value];
        }

        return null;
    }

    /**
     * Maps an attributes_json key to a naming candidate.
     *
     * @return array{priority:int, value:string}|null
     */
    private function candidateFromAliensKey(string $key, string $value): ?array
    {
        $k = mb_strtolower($key);

        if (preg_match('/\b(größe|groesse|size)\b/u', $k)) {
            return ['priority' => 10, 'value' => $value];
        }

        if (preg_match('/\bversion\b/u', $k)) {
            return ['priority' => 20, 'value' => $value];
        }

        if (preg_match('/\b(seil)?(länge|laenge|length)\b/u', $k)) {
            return ['priority' => 30, 'value' => $value];
        }

        if (preg_match('/\b(seil)?farbe\b/u', $k) || preg_match('/\bcolor\b/u', $k)) {
            return ['priority' => 40, 'value' => $value];
        }

        return null;
    }

    /**
     * Normalisiert einen Attributwert für das Product Naming.
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
