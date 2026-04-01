<?php

namespace App\Services\Categories;

use App\Models\Product;

/**
 * Synchronisiert die Kategorien eines Produkts anhand seines Namens.
 *
 * Regeln:
 * - automatisch gesetzte Kategorien dürfen aktualisiert und entfernt werden
 * - manuell gesetzte Kategorien bleiben immer erhalten
 * - pro Produkt/Kategorie existiert genau eine Pivot-Zeile
 * - wenn eine Kategorie bereits manuell gesetzt ist, darf die Automatik sie
 *   weder überschreiben noch entfernen
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

        $resolvedCategories = app(CategoryResolver::class)
            ->resolveFromProductName($nameForCategoryMatch);

        $resolvedCategoryIds = $resolvedCategories
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        $existingAssignments = $product->categories()
            ->pluck('category_product.assignment_type', 'categories.id');

        $manualCategoryIds = $existingAssignments
            ->filter(fn(string $type): bool => $type === 'manual')
            ->keys()
            ->map(fn($id): int => (int) $id)
            ->all();

        $autoCategoryIds = $existingAssignments
            ->filter(fn(string $type): bool => $type === 'auto')
            ->keys()
            ->map(fn($id): int => (int) $id)
            ->all();

        $autoCategoryIdsToDetach = array_diff($autoCategoryIds, $resolvedCategoryIds);

        if ($autoCategoryIdsToDetach !== []) {
            $product->categories()->detach($autoCategoryIdsToDetach);
        }

        $autoAssignmentsToAttach = [];

        foreach ($resolvedCategoryIds as $categoryId) {
            if (in_array($categoryId, $manualCategoryIds, true)) {
                continue;
            }

            $autoAssignmentsToAttach[$categoryId] = [
                'assignment_type' => 'auto',
            ];
        }

        if ($autoAssignmentsToAttach !== []) {
            $product->categories()->syncWithoutDetaching($autoAssignmentsToAttach);
        }
    }
}
