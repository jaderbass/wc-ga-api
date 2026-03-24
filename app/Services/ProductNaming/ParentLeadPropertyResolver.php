<?php

namespace App\Services\ProductNaming;

/**
 * Resolves the single lead property that may appear in a variable parent name.
 *
 * Rules:
 * - If there is only one attribute type across all variations, no parent property is shown.
 * - Otherwise, the lead property depends on the resolved product category.
 * - Fallback is based on a generic preference order.
 */
final class ParentLeadPropertyResolver
{
    /**
     * Resolves the lead property value for a variable parent product.
     *
     * @param array<string, array<int, string>> $propertyGroups
     */
    public function resolve(string $categoryName, string $designation, array $propertyGroups): ?string
    {
        $groups = $this->normalizeGroups($propertyGroups);

        if ($categoryName !== '' && mb_strtolower(trim($categoryName)) === 'seile') {
            $diameter = $this->extractRopeDiameterFromDesignation($designation);

            if ($diameter !== null) {
                return $diameter;
            }
        }

        if ($groups === []) {
            return null;
        }

        $priority = $this->priorityForCategory($categoryName);

        foreach ($priority as $type) {
            if (! empty($groups[$type])) {
                return $groups[$type][0] ?? null;
            }
        }

        foreach ($groups as $values) {
            if (! empty($values)) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Extracts the rope diameter from the designation.
     *
     * Examples:
     * - "Statikseil 11.0 Patron" -> "11mm"
     * - "Rope 10,5"              -> "10,5mm"
     */
    private function extractRopeDiameterFromDesignation(string $designation): ?string
    {
        $designation = trim($designation);

        if ($designation === '') {
            return null;
        }

        if (! preg_match('/\b(\d{1,2}(?:[.,]\d)?)\b/u', $designation, $matches)) {
            return null;
        }

        $raw = trim((string) ($matches[1] ?? ''));

        if ($raw === '') {
            return null;
        }

        $normalized = str_replace('.', '', $raw);

        if (str_contains($raw, ',') || str_contains($raw, '.')) {
            $normalized = str_replace('.', ',', $raw);
        }

        return $normalized . 'mm';
    }

    /**
     * @param array<string, array<int, string>> $groups
     * @return array<string, array<int, string>>
     */
    private function normalizeGroups(array $groups): array
    {
        $result = [];

        foreach ($groups as $type => $values) {
            $filtered = array_values(array_filter(
                $values,
                static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
            ));

            if ($filtered === []) {
                continue;
            }

            $filtered = array_values(array_unique(array_map('trim', $filtered)));
            sort($filtered, SORT_NATURAL | SORT_FLAG_CASE);

            $result[$type] = $filtered;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function priorityForCategory(string $categoryName): array
    {
        $category = mb_strtolower(trim($categoryName));

        if ($category === 'seile') {
            return ['durchmesser', 'groesse', 'laenge', 'farbe', 'version'];
        }

        if ($category === 'handschuhe') {
            return ['groesse', 'farbe', 'version', 'laenge', 'durchmesser'];
        }

        if ($category === 'karabiner') {
            return ['version', 'groesse', 'farbe', 'laenge', 'durchmesser'];
        }

        return ['groesse', 'version', 'durchmesser', 'laenge', 'farbe'];
    }
}
