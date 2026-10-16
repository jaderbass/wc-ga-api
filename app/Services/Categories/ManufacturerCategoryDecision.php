<?php

namespace App\Services\Categories;

use App\Models\Category;
use App\Models\CategoryAssignmentRule;
use Illuminate\Support\Collection;

/**
 * Ergebnis der Hersteller-Zuordnung für ein Produkt.
 */
final class ManufacturerCategoryDecision
{
    /**
     * @param  Collection<int, Category>  $categories
     */
    public function __construct(
        public readonly Collection $categories,
        public readonly bool $exclude,
        public readonly CategoryAssignmentRule $rule,
    ) {}

    public static function fromRule(CategoryAssignmentRule $rule): self
    {
        return new self($rule->categories->values(), (bool) $rule->exclude, $rule);
    }
}
