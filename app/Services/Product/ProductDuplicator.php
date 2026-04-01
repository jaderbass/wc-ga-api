<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;

/**
 * Dupliziert ein Produkt samt Kategorien und Varianten.
 *
 * Ziel:
 * - Fachliche Produktdaten übernehmen
 * - Kategorien inkl. assignment_type übernehmen
 * - Varianten inkl. AttributeValues übernehmen
 * - Woo-/Sync-relevante Felder sauber zurücksetzen
 *
 * Nicht Bestandteil der MVP-Version:
 * - product_meta
 * - product_images
 * - Historie / Herkunftsverweis
 */
class ProductDuplicator
{
    /**
     * Dupliziert das übergebene Produkt.
     *
     * @param Product $sourceProduct
     * @return Product
     */
    public function duplicate(Product $sourceProduct): Product
    {
        return DB::transaction(function () use ($sourceProduct): Product {
            $sourceProduct->loadMissing([
                'categories',
                'variations.attributeValues',
            ]);

            $newProduct = $this->duplicateProduct($sourceProduct);

            $this->duplicateCategories($sourceProduct, $newProduct);
            $this->duplicateVariations($sourceProduct, $newProduct);

            return $newProduct->fresh([
                'categories',
                'variations.attributeValues',
            ]);
        });
    }

    /**
     * Erstellt den neuen Produkt-Datensatz.
     *
     * @param Product $sourceProduct
     * @return Product
     */
    protected function duplicateProduct(Product $sourceProduct): Product
    {
        $newProduct = $sourceProduct->replicate([
            'woo_product_id',
            'sku',
            'slug',
            'created_at',
            'updated_at',
        ]);

        // Neue Identität erzwingen
        $newProduct->woo_product_id = null;
        $newProduct->sku = null;
        $newProduct->slug = null; // wird im Model automatisch neu erzeugt

        $newProduct->save();

        return $newProduct;
    }

    /**
     * Übernimmt Kategorien inkl. assignment_type.
     *
     * @param Product $sourceProduct
     * @param Product $newProduct
     * @return void
     */
    protected function duplicateCategories(Product $sourceProduct, Product $newProduct): void
    {
        $syncData = $sourceProduct->categories
            ->mapWithKeys(function ($category): array {
                return [
                    (int) $category->id => [
                        'assignment_type' => $category->pivot->assignment_type ?? 'manual',
                    ],
                ];
            })
            ->all();

        if ($syncData !== []) {
            $newProduct->categories()->sync($syncData);
        }
    }

    /**
     * Übernimmt Varianten und deren AttributeValues.
     *
     * @param Product $sourceProduct
     * @param Product $newProduct
     * @return void
     */
    protected function duplicateVariations(Product $sourceProduct, Product $newProduct): void
    {
        foreach ($sourceProduct->variations as $sourceVariation) {
            $newVariation = $this->duplicateVariation($sourceVariation, $newProduct);

            $attributeValueIds = $sourceVariation->attributeValues
                ->pluck('id')
                ->map(fn($id): int => (int) $id)
                ->all();

            if ($attributeValueIds !== []) {
                $newVariation->attributeValues()->sync($attributeValueIds);
            }
        }
    }

    /**
     * Erstellt eine neue Variante auf Basis der Quell-Variante.
     *
     * Preise werden bewusst nicht übernommen, da sie im Projekt
     * nicht verwendet werden sollen.
     *
     * @param ProductVariation $sourceVariation
     * @param Product $newProduct
     * @return ProductVariation
     */
    protected function duplicateVariation(ProductVariation $sourceVariation, Product $newProduct): ProductVariation
    {
        $newVariation = $sourceVariation->replicate([
            'woo_variation_id',
            'sku',
            'external_id',
            'regular_price',
            'sale_price',
            'created_at',
            'updated_at',
        ]);

        $newVariation->product_id = $newProduct->id;
        $newVariation->woo_variation_id = null;
        $newVariation->sku = null;
        $newVariation->external_id = null;
        $newVariation->regular_price = null;
        $newVariation->sale_price = null;

        $newVariation->save();

        return $newVariation;
    }
}
