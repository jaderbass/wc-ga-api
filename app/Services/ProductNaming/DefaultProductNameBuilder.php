<?php

namespace App\Services\ProductNaming;

use App\Support\ProductNameNormalizer;

/**
 * Builds the Woo product name according to customer rules.
 *
 * Customer rules (summary):
 * - Manufacturer name in CAPS
 * - Designation normalized with capitalized words
 * - Parts separated by " - "
 * - Simple: Manufacturer - Category - Designation - P1 - P2 - P3
 * - Variable: Manufacturer - Category - Designation - P1
 * - Set: currently treated like Simple, may get a dedicated rule later
 *
 * Notes:
 * - The builder does NOT decide category or property order.
 * - Empty parts are removed automatically to avoid duplicate separators.
 */
final class DefaultProductNameBuilder
{
    public function __construct(
        private readonly NameTemplateRegistry $registry,
    ) {}

    public function build(ProductNameContext $ctx): ProductNameResult
    {
        $tpl = $this->registry->get($ctx);
        $separator = (string) $tpl['separator'];
        $template = (array) $tpl['template'];

        $properties = $this->resolveNameProperties($ctx);

        /** @var array<string, string|null> $tokens */
        $tokens = [
            'manufacturer' => $this->normalizeManufacturer($ctx->manufacturerName),
            'category'     => $this->normalizePart($ctx->categoryName),
            'designation'  => $this->normalizeDesignation(
                $ctx->designation,
                $ctx->manufacturerName
            ),
            'p1'           => $this->normalizePart($properties[0] ?? null),
            'p2'           => $this->normalizePart($properties[1] ?? null),
            'p3'           => $this->normalizePart($properties[2] ?? null),
        ];

        $parts = [];
        foreach ($template as $token) {
            $val = $tokens[$token] ?? null;
            if ($val !== null && $val !== '') {
                $parts[] = $val;
            }
        }

        $name = $this->joinAndCleanup($parts, $separator);

        return new ProductNameResult(productName: $name, parts: $parts);
    }

    /**
     * Returns the properties that may appear in the final product name.
     *
     * Rules:
     * - Simple: max. 3 properties
     * - Variable: max. 1 property
     * - Set: currently max. 3 properties
     *
     * @return array<int, string>
     */
    private function resolveNameProperties(ProductNameContext $ctx): array
    {
        $properties = array_values(array_filter(
            $ctx->properties,
            static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
        ));

        return array_slice($properties, 0, $ctx->kind->propertyLimit());
    }

    /**
     * Manufacturer must be uppercased.
     */
    private function normalizeManufacturer(string $name): string
    {
        $name = trim($name);

        return $name === '' ? '' : mb_strtoupper($name);
    }

    /**
     * Normalizes the designation according to customer naming rules.
     *
     * Rules:
     * - duplicated manufacturer prefix is removed
     * - product designation is normalized via ProductNameNormalizer
     */
    private function normalizeDesignation(string $designation, string $manufacturer): string
    {
        $designation = $this->removeManufacturerPrefix($designation, $manufacturer);
        $designation = trim($designation);

        if ($designation === '') {
            return '';
        }

        return ProductNameNormalizer::normalize($designation);
    }

    /**
     * Normalizes a generic part (category/properties).
     *
     * Empty strings become null to make part filtering easier.
     */
    private function normalizePart(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim($value);

        return $v === '' ? null : $v;
    }

    /**
     * Joins parts and cleans whitespace.
     *
     * @param array<int, string> $parts
     */
    private function joinAndCleanup(array $parts, string $separator): string
    {
        $name = implode($separator, $parts);

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Removes duplicated manufacturer prefix from designation.
     *
     * Example:
     * "ALIENS Aufreissfalldämpfer Reactor Rope"
     * -> "Aufreissfalldämpfer Reactor Rope"
     */
    private function removeManufacturerPrefix(string $designation, string $manufacturer): string
    {
        $designation = trim($designation);
        $manufacturer = trim($manufacturer);

        if ($designation === '' || $manufacturer === '') {
            return $designation;
        }

        $quoted = preg_quote($manufacturer, '/');

        $updated = preg_replace(
            '/^' . $quoted . '\b(?:\s*[-–—:|]\s*|\s+)/iu',
            '',
            $designation,
            1
        );

        return is_string($updated) ? trim($updated) : $designation;
    }
}
