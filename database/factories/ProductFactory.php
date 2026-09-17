<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for Product model.
 *
 * Ensures required NOT NULL and unique columns are filled:
 * - product_type (NOT NULL)
 * - stock_status (NOT NULL)
 * - slug (NOT NULL, UNIQUE)
 *
 * `product_name` is nullable in your schema, so we keep it null by default.
 */
class ProductFactory extends Factory
{
  protected $model = Product::class;

  public function definition(): array
  {
    // Prefer UUID-based slug to avoid faker unique pool exhaustion in large suites.
    $slug = Str::slug($this->faker->words(3, true)) . '-' . Str::lower(Str::uuid()->toString());

    return [
      'manufacturer_id' => null,

      // NOT NULL
      'product_type' => 'simple',

      // NOT NULL + UNIQUE
      'slug' => $slug,

      // Nullable
      'product_name' => null,

      // Column exists (nullable), safe default
      'original_product_name' => null,

      // NOT NULL
      'stock_status' => 'in_stock',
    ];
  }

  /**
   * Convenience state to force a product_name.
   */
  public function withProductName(?string $name = 'Test Product'): static
  {
    return $this->state(fn() => [
      'product_name' => $name,
    ]);
  }
}
