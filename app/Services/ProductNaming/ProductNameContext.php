<?php

namespace App\Services\ProductNaming;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Categories\CategoryResolver;

/**
 * Input data for the ProductNameBuilder.
 *
 * This context is intentionally import- and sync-agnostic:
 * - No DB calls
 * - No Woo calls
 * - No implicit mapping logic
 *
 * Your importer/sync layer prepares:
 * - manufacturerName (for CAPS)
 * - categoryName (resolved from designation via CategoryResolver)
 * - designation (CSV original name)
 * - properties[] in the correct order (property1..propertyN)
 */
final class ProductNameContext
{
    /**
     * @param  array<int, string|null>  $properties
     */
    public function __construct(
        public readonly ProductKind $kind,
        public readonly string $manufacturerName,
        public readonly string $categoryName,
        public readonly string $designation,
        /** @var array<int, string|null> [p1, p2, p3] */
        public readonly array $properties = [],
        public readonly ?int $manufacturerId = null,
    ) {}

    /**
     * Builds a naming context from a Product model.
     *
     * Notes:
     * - designation is taken from `original_product_name` (preferred),
     *   falls back to `product_name` and finally `slug`.
     * - properties are extracted via ProductPropertyExtractor (pivot or attributes_json fallback).
     */
    public static function fromProduct(Product $product): self
    {
        /** @var ProductPropertyExtractor $extractor */
        $extractor = app(ProductPropertyExtractor::class);

        $manufacturerName = '';
        if ($product->relationLoaded('manufacturer') && $product->manufacturer) {
            $manufacturerName = (string) ($product->manufacturer->manufacturer ?? '');
        } else {
            $m = Manufacturer::query()->find($product->manufacturer_id);
            $manufacturerName = $m ? (string) ($m->manufacturer ?? '') : '';
        }

        $designation =
            (is_string($product->original_product_name) && trim($product->original_product_name) !== '')
            ? trim($product->original_product_name)
            : (string) $product->slug;

        $categoryName = self::resolveCategoryName($designation);

        $kind = self::resolveKind($product);

        $properties = collect($extractor->extract($product) ?? [])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->unique()
            ->values()
            ->all();

        return new self(
            kind: $kind,
            manufacturerName: $manufacturerName,
            categoryName: $categoryName,
            designation: $designation,
            properties: $properties,
            manufacturerId: (int) $product->manufacturer_id,
        );
    }

    /**
     * Resolves the naming kind from the persisted product type.
     */
    public static function resolveKind(Product $product): ProductKind
    {
        return match ($product->product_type) {
            'variable' => ProductKind::Variable,
            'set' => ProductKind::Set,
            default => ProductKind::Simple,
        };
    }

    /**
     * Builds a naming context from a ProductVariation model.
     *
     * Notes:
     * - Base data (manufacturer, category, designation, manufacturerId) comes
     *   from the parent product.
     * - Properties come from the variation itself.
     * - Variations always use ProductKind::Variable for naming.
     */
    public static function fromVariation(ProductVariation $variation): self
    {
        $variation->loadMissing([
            'product.manufacturer',
            'attributeValues.attribute',
        ]);

        $product = $variation->product;

        if (! $product instanceof Product) {
            return new self(
                kind: ProductKind::Variable,
                manufacturerName: '',
                categoryName: '',
                designation: '',
                properties: [],
                manufacturerId: null,
            );
        }

        $base = self::fromProduct($product);

        $properties = [];

        if ($variation->relationLoaded('attributeValues')) {
            foreach ($variation->attributeValues as $attributeValue) {
                $value = trim((string) ($attributeValue->value ?? ''));

                if ($value !== '') {
                    $properties[] = $value;
                }
            }
        }

        if ($properties === []) {
            $json = $variation->attributes_json;

            if (is_array($json)) {
                foreach ($json as $rawValue) {
                    $value = trim((string) $rawValue);

                    if ($value !== '') {
                        $properties[] = $value;
                    }
                }
            }
        }

        $properties = array_values(array_unique($properties));

        return new self(
            kind: ProductKind::Variable,
            manufacturerName: $base->manufacturerName,
            categoryName: $base->categoryName,
            designation: $base->designation,
            properties: $properties,
            manufacturerId: $base->manufacturerId,
        );
    }

    /**
     * Resolves the first matching category name for a designation.
     *
     * Falls nothing matches, the CategoryResolver currently falls back to
     * "Allgemein".
     */
    public static function resolveCategoryName(string $designation): string
    {
        /** @var CategoryResolver $categoryResolver */
        $categoryResolver = app(CategoryResolver::class);

        return (string) optional(
            $categoryResolver->resolveFromProductName($designation)->first()
        )->name;
    }
}
