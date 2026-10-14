<?php

namespace App\Services\ProductNaming;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Welche Kategorie steht im Produktnamen?
 *
 * Grundlage sind die Kategorien, die dem Produkt zugeordnet sind (manuell,
 * Hersteller-Zuordnung, Stichwort-Regeln – siehe ProductCategorySyncService).
 * Nicht in Frage kommen "Allgemein", "SALE" und Einsatzgebiete.
 *
 * Auswahl: manuell gesetzte vor automatischen, dann die speziellste
 * (tiefste) Kategorie, dann die kleinste ID.
 *
 * Im Namen steht die "Bezeichnung im Produktnamen" der Kategorie
 * (categories.name_label), sonst ihr Name. Hat das Produkt keine passende
 * Kategorie, bleibt es bei den Stichwort-Regeln am Namen (bisheriges Verhalten).
 */
class ProductNameCategory
{
    protected const IGNORED = ['allgemein', 'sale'];

    protected const IGNORED_BRANCHES = ['einsatzgebiet'];

    /** Oberkategorie der Produktkategorien ("Produkte Übersicht > Produkte") */
    protected const PRODUCT_ROOT = 'produkte';

    public static function primary(Product $product): ?Category
    {
        if (! $product->exists) {
            return null;
        }

        /** @var Collection<int, Category> $categories */
        $categories = $product->categories()->with('parent.parent.parent')->get();

        return $categories
            ->reject(fn (Category $category): bool => self::isIgnored($category))
            ->sortBy(fn (Category $category): array => [
                ($category->pivot?->assignment_type ?? 'auto') === 'manual' ? 0 : 1,
                -count(self::path($category)),
                $category->id,
            ])
            ->first();
    }

    /**
     * Kategorie-Bezeichnung für den Namen; Fallback: Stichwort-Regeln am Namen.
     */
    public static function nameFor(Product $product, string $designation): string
    {
        $category = self::primary($product);

        if ($category !== null) {
            return trim((string) ($category->name_label ?: $category->name));
        }

        return ProductNameContext::resolveCategoryName($designation);
    }

    /**
     * Produktgruppe für die Leit-Eigenschaft (Seile, Karabiner, Handschuhe …):
     * die Kategorie direkt unter "Produkte", z. B. "Statisch" → "Seile".
     */
    public static function leadGroupFor(Product $product, string $designation): string
    {
        $category = self::primary($product);

        if ($category === null) {
            return ProductNameContext::resolveCategoryName($designation);
        }

        $path = self::path($category);
        $rootIndex = array_search(self::PRODUCT_ROOT, array_map(fn (Category $c) => mb_strtolower($c->name), $path), true);

        if ($rootIndex !== false && isset($path[$rootIndex + 1])) {
            return $path[$rootIndex + 1]->name;
        }

        return $category->name;
    }

    protected static function isIgnored(Category $category): bool
    {
        if (in_array(mb_strtolower($category->name), self::IGNORED, true)) {
            return true;
        }

        foreach (self::path($category) as $level) {
            if (in_array(mb_strtolower($level->name), self::IGNORED_BRANCHES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pfad von oben nach unten, z. B. [Produkte Übersicht, Produkte, Seile, Statisch].
     *
     * @return list<Category>
     */
    protected static function path(Category $category): array
    {
        $path = [$category];
        $current = $category;
        $guard = 0;

        while ($current->parent_id !== null && $guard++ < 10) {
            $current = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();

            if ($current === null) {
                break;
            }

            array_unshift($path, $current);
        }

        return $path;
    }
}
