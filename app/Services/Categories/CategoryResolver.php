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
     * Keyword-zu-Kategorie-Mapping.
     *
     * @var array<string, string>
     */
    protected array $rules = [
        'gurt' => 'Sitz- oder Arbeitsgurte',

        'seil' => 'Seile',
        'reepschnur' => 'Seile',
        'reepschnüre' => 'Seile',

        'karabiner' => 'Karabiner',

        'helm' => 'Schutzhelme',
    ];

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
        $productName = Str::lower((string) $productName);

        $matchedCategoryNames = collect();

        foreach ($this->rules as $keyword => $categoryName) {
            if (Str::contains($productName, $keyword)) {
                $matchedCategoryNames->push($categoryName);
            }
        }

        $matchedCategoryNames = $matchedCategoryNames
            ->unique()
            ->values();

        if ($matchedCategoryNames->isEmpty()) {
            $matchedCategoryNames = collect(['Allgemein']);
        }

        return Category::query()
            ->whereIn('name', $matchedCategoryNames->all())
            ->get();
    }
}
