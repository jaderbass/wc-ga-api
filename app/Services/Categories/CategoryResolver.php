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
     * Kategorie-zu-Keyword-Mapping.
     *
     * @var array<string, list<string>>
     */
    protected array $rules = [
        'Sitz- oder Arbeitsgurte' => [
            'gurt',
            'gurte',
        ],
        'Seile' => [
            'seil',
            'seile',
            'reepschnur',
            'reepschnüre',
        ],
        'Karabiner' => [
            'karabiner',
            'karabiners',
        ],
        'Verbindungsmittel' => [
            'karabiner',
            'karabiners',
        ],
        'Schutzhelme' => [
            'helm',
            'helme',
        ],
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
        $normalizedName = $this->normalizeProductName($productName);

        $matchedCategoryNames = collect();

        foreach ($this->rules as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalizedName, $keyword)) {
                    $matchedCategoryNames->push($categoryName);
                    break;
                }
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
}
