<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Rebuilds and persists the generated product name and slug for a product.
 *
 * Responsibilities:
 * - Build the current product name from ProductNameContext
 * - Generate the slug from the computed product name
 * - Ensure slug uniqueness within the manufacturer scope
 * - Persist both values on the product
 */
final class ProductNameUpdater
{
    public function __construct(
        private readonly DefaultProductNameBuilder $builder,
    ) {}

    /**
     * Computes the current product name based on the latest naming rules.
     *
     * This method:
     * - builds a ProductNameContext from the given product
     * - applies the DefaultProductNameBuilder
     * - returns the full ProductNameResult (name, tokens, parts, etc.)
     *
     * It does NOT persist any changes to the database.
     *
     * Use this method when:
     * - previewing names (e.g. in commands)
     * - comparing current DB values with computed names
     * - debugging naming logic
     */
    public function compute(Product $product): ProductNameResult
    {
        $product->refresh()->load([
            'manufacturer',
            'variations.attributeValues.attribute',
        ]);

        $ctx = ProductNameContext::fromProduct($product);

        return $this->builder->build($ctx);
    }

    /**
     * Rebuilds and persists the product name and slug for the given product.
     *
     * This method:
     * - computes the current product name using the naming pipeline
     * - generates a slug from the computed product name
     * - ensures slug uniqueness within the same manufacturer
     * - updates both product_name and slug on the model
     *
     * It is the central entry point for all write operations related to naming.
     *
     * Use this method in:
     * - importers
     * - backfill / rebuild commands
     * - UI actions (e.g. "Produktnamen neu generieren")
     */
    public function update(Product $product): ProductNameResult
    {
        $result = $this->compute($product);

        $productName = trim($result->productName);
        $slug = $this->buildUniqueSlug($product, $productName);

        $product->update([
            'product_name' => $productName,
            'slug' => $slug,
        ]);

        return $result;
    }

    /**
     * Builds a unique slug for the given product based on its computed name.
     *
     * Strategy:
     * - Generate a base slug using Str::slug()
     * - If empty, fall back to a deterministic placeholder
     * - Ensure uniqueness within the same manufacturer by appending a numeric suffix
     *
     * Example:
     * - base: "petzl-seile-volta-60m"
     * - collision: "petzl-seile-volta-60m-1", "petzl-seile-volta-60m-2", ...
     *
     * @param Product $product
     * @param string  $productName The computed product name
     *
     * @return string Unique slug for the product
     */
    private function buildUniqueSlug(Product $product, string $productName): string
    {
        $baseSlug = Str::slug($productName);

        if ($baseSlug === '') {
            $baseSlug = 'produkt-' . $product->id;
        }

        $slug = $baseSlug;
        $suffix = 1;

        while (
            Product::query()
            ->where('manufacturer_id', $product->manufacturer_id)
            ->where('slug', $slug)
            ->whereKeyNot($product->id)
            ->exists()
        ) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
