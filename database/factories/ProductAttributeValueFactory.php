<?php

namespace Database\Factories;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for ProductAttributeValue model.
 */
class ProductAttributeValueFactory extends Factory
{
  protected $model = ProductAttributeValue::class;

  public function definition(): array
  {
    return [
      'attribute_id' => ProductAttribute::factory(),
      'value' => $this->faker->word,
      'slug' => $this->faker->slug(2),
      'woo_term_id' => null,
    ];
  }
}
