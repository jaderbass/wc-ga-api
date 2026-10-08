<?php

namespace App\Services\Categories;

use App\Models\Category;
use App\Models\CategoryAssignmentRule;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Synchronisiert die Kategorien eines Produkts.
 *
 * Quelle der automatischen Kategorien (erste, die greift):
 * 1. Hersteller-Zuordnung ({@see ManufacturerCategoryResolver}):
 *    Stichwort-Regeln, dann Zuordnung der Herstellerkategorie.
 *    "Ausschließen" → keine automatischen Kategorien (manuelle bleiben;
 *    den Shop-Export beeinflusst das noch nicht).
 * 2. allgemeine Stichwort-Regeln am Produktnamen ({@see CategoryResolver}),
 *    sonst "Allgemein"
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
    public function __construct(
        protected ManufacturerCategoryResolver $manufacturerResolver,
    ) {}

    /**
     * Synchronisiert die Kategorien eines Produkts.
     */
    public function sync(Product $product): void
    {
        CategoryAssignmentRule::ensureForSource(
            $product->manufacturer_id !== null ? (int) $product->manufacturer_id : null,
            $product->source_category,
        );

        $resolvedCategories = $this->resolveCategories($product);

        $resolvedCategoryIds = $resolvedCategories
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        $existingAssignments = $product->categories()
            ->pluck('category_product.assignment_type', 'categories.id');

        // manuell und beim Import gesetzte (z. B. SALE aus der Preisliste) bleiben unangetastet
        $manualCategoryIds = $existingAssignments
            ->filter(fn(string $type): bool => $type !== 'auto')
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

    /**
     * Zwischengespeicherte Zuordnungsregeln verwerfen (nach Änderungen im selben Prozess).
     */
    public function flushRules(): void
    {
        $this->manufacturerResolver->flush();
    }

    /**
     * @return Collection<int, Category>
     */
    protected function resolveCategories(Product $product): Collection
    {
        $decision = $this->manufacturerResolver->resolve($product);

        if ($decision !== null) {
            return $decision->exclude ? collect() : $decision->categories;
        }

        $nameForCategoryMatch = $product->product_name
            ?: $product->original_product_name
            ?: null;

        return app(CategoryResolver::class)->resolveFromProductName($nameForCategoryMatch);
    }
}
