<?php

namespace Tests\Unit\ProductNaming;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\ProductNaming\ProductPropertyExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit test for ProductPropertyExtractor JSON fallback.
 *
 * Does not use database or migrations.
 * We only verify the priority mapping from attributes_json -> [p1, p2, p3].
 */
class ProductPropertyExtractorJsonFallbackTest extends TestCase
{
  #[Test]
  public function it_extracts_properties_from_attributes_json_using_priority_rules(): void
  {
    $product = new Product();
    $variation = new ProductVariation();

    // Simulate Aliens attributes_json on the variation
    $variation->attributes_json = [
      'Attribute Group: Seil-Version' => 'Standard',
      'Attribute Group: Seillänge'    => '30 Meter',
      'Attribute Group: Seilfarbe'    => 'Blau',
    ];

    // Attach variation to product relation in-memory
    $product->setRelation('variations', collect([$variation]));
    $variation->setRelation('attributeValues', collect([]));

    $extractor = app(ProductPropertyExtractor::class);

    // IMPORTANT:
    // If your extractor calls $product->loadMissing(...),
    // it will try to hit the DB. In that case, we need a small guard in the extractor:
    // - if relation 'variations' is already loaded, don't call loadMissing
    $props = $extractor->extract($product);

    $this->assertSame(['Standard', '30 Meter', 'Blau'], $props);
  }
}
