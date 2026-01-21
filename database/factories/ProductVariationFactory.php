<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for ProductVariation model.
 *
 * Variations must always belong to a product.
 */
class ProductVariationFactory extends Factory
{
  protected $model = ProductVariation::class;

  public function definition(): array
  {
    $id = $this->faker->unique()->numberBetween(100000, 999999);

    return [
      'product_id'  => Product::factory(),
      'external_id' => 'TEST-' . $id,
      'sku'         => 'TEST-SKU-' . $id,
    ];
  }
}
