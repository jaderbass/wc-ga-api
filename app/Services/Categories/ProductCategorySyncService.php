<?php

namespace App\Services\Categories;

use App\Models\Product;

/**
 * Synchronisiert die Kategorien eines Produkts anhand seines Namens.
 */
class ProductCategorySyncService
{
    /**
     * Synchronisiert die Kategorien eines Produkts.
     */
    public function sync(Product $product): void
    {
        $nameForCategoryMatch = $product->product_name
            ?: $product->original_product_name
            ?: null;

        $categories = app(CategoryResolver::class)
            ->resolveFromProductName($nameForCategoryMatch);

        $categoryIds = $categories->pluck('id')->all();

        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }
    }
}
