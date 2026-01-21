<?php

namespace Database\Factories;

use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for ProductAttribute model.
 */
class ProductAttributeFactory extends Factory
{
  protected $model = ProductAttribute::class;

  public function definition(): array
  {
    return [
      'name' => ucfirst($this->faker->word),
      'slug' => $this->faker->slug(1),
      'woo_attribute_id' => null,
    ];
  }
}
