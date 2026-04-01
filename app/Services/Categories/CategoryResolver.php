<?php

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ermittelt Kategorien anhand des Produktnamens.
 */
class CategoryResolver
{
    /**
     * Ermittelt passende Kategorien für einen Produktnamen.
     *
     * Wenn keine Regel greift, wird "Allgemein" zurückgegeben.
     *
     * @param string|null $productName
     * @return Collection<int, Category>
     */
    public function resolveFromProductName(?string $productName): Collection
    {
        $normalizedName = $this->normalizeProductName($productName);

        $categories = Category::query()
            ->with(['rules'])
            ->get();

        $matchedCategories = $categories->filter(function (Category $category) use ($normalizedName): bool {
            foreach ($category->rules as $rule) {
                $keyword = $this->normalizeKeyword($rule->keyword);

                if ($keyword !== '' && Str::contains($normalizedName, $keyword)) {
                    return true;
                }
            }

            return false;
        })->values();

        if ($matchedCategories->isEmpty()) {
            return Category::query()
                ->where('name', 'Allgemein')
                ->get();
        }

        return $matchedCategories;
    }

    /**
     * Normalisiert den Produktnamen für die Keyword-Suche.
     */
    protected function normalizeProductName(?string $productName): string
    {
        $value = Str::lower((string) $productName);
        $value = str_replace(['/', '-', '_', ',', '.', ';', ':'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Normalisiert ein Keyword für die Suche.
     */
    protected function normalizeKeyword(?string $keyword): string
    {
        $value = Str::lower((string) $keyword);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
