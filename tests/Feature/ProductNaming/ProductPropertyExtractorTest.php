<?php

namespace Tests\Feature\ProductNaming;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariation;
use App\Services\ProductNaming\ProductPropertyExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for extracting ordered naming properties from product variation attribute values.
 *
 * This test hits the database to ensure:
 * - relationships are wired correctly
 * - extractor collects values via variations -> attributeValues -> attribute
 * - order is deterministic (size, length, color)
 * - missing attributes result in null
 * - first value wins for duplicate attribute slugs
 */
class ProductPropertyExtractorTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function it_extracts_properties_in_customer_order(): void
  {
    $product = Product::factory()->create();

    $variation = ProductVariation::factory()->create([
      'product_id' => $product->id,
    ]);

    $sizeAttr = ProductAttribute::query()->create([
      'name' => 'Size',
      'slug' => 'size',
      'woo_attribute_id' => null,
    ]);

    $lengthAttr = ProductAttribute::query()->create([
      'name' => 'Length',
      'slug' => 'length',
      'woo_attribute_id' => null,
    ]);

    $colorAttr = ProductAttribute::query()->create([
      'name' => 'Color',
      'slug' => 'color',
      'woo_attribute_id' => null,
    ]);

    $sizeVal = ProductAttributeValue::query()->create([
      'attribute_id' => $sizeAttr->id,
      'value' => '0.5',
      'slug' => '0-5',
      'woo_term_id' => null,
    ]);

    $lengthVal = ProductAttributeValue::query()->create([
      'attribute_id' => $lengthAttr->id,
      'value' => '90 mm',
      'slug' => '90-mm',
      'woo_term_id' => null,
    ]);

    $colorVal = ProductAttributeValue::query()->create([
      'attribute_id' => $colorAttr->id,
      'value' => 'red',
      'slug' => 'red',
      'woo_term_id' => null,
    ]);

    $variation->attributeValues()->attach([$sizeVal->id, $lengthVal->id, $colorVal->id]);

    $extractor = app(ProductPropertyExtractor::class);
    $props = $extractor->extract($product->fresh());

    $this->assertSame(['0.5', '90 mm', 'red'], $props);
  }

  #[Test]
  public function it_returns_null_for_missing_attributes(): void
  {
    $product = Product::factory()->create();
    $variation = ProductVariation::factory()->create(['product_id' => $product->id]);

    $sizeAttr = ProductAttribute::query()->create([
      'name' => 'Size',
      'slug' => 'size',
      'woo_attribute_id' => null,
    ]);

    $sizeVal = ProductAttributeValue::query()->create([
      'attribute_id' => $sizeAttr->id,
      'value' => '1.0',
      'slug' => '1-0',
      'woo_term_id' => null,
    ]);

    $variation->attributeValues()->attach([$sizeVal->id]);

    $extractor = app(ProductPropertyExtractor::class);
    $props = $extractor->extract($product->fresh());

    // Order is [size, length, color]
    $this->assertSame(['1.0', null, null], $props);
  }

  #[Test]
  public function it_uses_first_value_if_multiple_variations_have_same_attribute(): void
  {
    $product = Product::factory()->create();

    $v1 = ProductVariation::factory()->create(['product_id' => $product->id]);
    $v2 = ProductVariation::factory()->create(['product_id' => $product->id]);

    $sizeAttr = ProductAttribute::query()->create([
      'name' => 'Size',
      'slug' => 'size',
      'woo_attribute_id' => null,
    ]);

    $sizeVal1 = ProductAttributeValue::query()->create([
      'attribute_id' => $sizeAttr->id,
      'value' => '0.3',
      'slug' => '0-3',
      'woo_term_id' => null,
    ]);

    $sizeVal2 = ProductAttributeValue::query()->create([
      'attribute_id' => $sizeAttr->id,
      'value' => '0.4',
      'slug' => '0-4',
      'woo_term_id' => null,
    ]);

    // Attach in order: v1 first, then v2
    $v1->attributeValues()->attach([$sizeVal1->id]);
    $v2->attributeValues()->attach([$sizeVal2->id]);

    $extractor = app(ProductPropertyExtractor::class);
    $props = $extractor->extract($product->fresh());

    $this->assertSame(['0.3', null, null], $props);
  }

  #[Test]
  public function it_extracts_properties_from_attributes_json_using_priority_rules(): void
  {
    $product = Product::factory()->create();

    $variation = ProductVariation::factory()->create([
      'product_id' => $product->id,
      // IMPORTANT: attributes_json must be cast to array in your model,
      // otherwise set it via update() and ensure casts exist.
      'attributes_json' => [
        'Attribute Group: Seil-Version' => 'Standard',
        'Attribute Group: Seillänge'    => '30 Meter',
        'Attribute Group: Seilfarbe'    => 'Blau',
      ],
    ]);

    // Ensure there are NO pivot-based attribute values so fallback is used.
    $variation->attributeValues()->detach();

    $extractor = app(ProductPropertyExtractor::class);
    $props = $extractor->extract($product->fresh());

    // Priority rules should yield: Version (p1), Länge (p2), Farbe (p3)
    $this->assertSame(['Standard', '30 Meter', 'Blau'], $props);
  }
}
