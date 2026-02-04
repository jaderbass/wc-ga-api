<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use App\Models\Manufacturer;

/**
 * Input data for the ProductNameBuilder.
 *
 * This context is intentionally import- and sync-agnostic:
 * - No DB calls
 * - No Woo calls
 * - No implicit mapping logic
 *
 * Your importer/sync layer prepares:
 * - manufacturerName (for CAPS)
 * - categoryName (currently from Woo for existing products)
 * - designation (CSV original name)
 * - properties[] in the correct order (property1..propertyN)
 */
final class ProductNameContext
{
  /**
   * @param array<int, string|null> $properties
   */
  public function __construct(
    public readonly ProductKind $kind,
    public readonly string $manufacturerName,
    public readonly string $categoryName,
    public readonly string $designation,
    /** @var array<int, string|null> [p1, p2, p3] */
    public readonly array $properties = [],
    public readonly ?int $manufacturerId = null,
  ) {}

  /**
   * Builds a naming context from a Product model.
   *
   * Notes:
   * - designation is taken from `original_product_name` (preferred),
   *   falls back to `product_name` and finally `slug`.
   * - category is currently unknown / managed in Woo, so it is empty for now.
   * - properties are extracted via ProductPropertyExtractor (pivot or attributes_json fallback).
   *
   * @param  Product  $product
   * @return self
   */
  public static function fromProduct(Product $product): self
  {
    /** @var ProductPropertyExtractor $extractor */
    $extractor = app(ProductPropertyExtractor::class);

    $manufacturerName = '';
    if ($product->relationLoaded('manufacturer') && $product->manufacturer) {
      $manufacturerName = (string) ($product->manufacturer->manufacturer ?? '');
    } else {
      $m = Manufacturer::query()->find($product->manufacturer_id);
      $manufacturerName = $m ? (string) ($m->manufacturer ?? '') : '';
    }

    $designation =
      (is_string($product->original_product_name) && trim($product->original_product_name) !== '')
        ? trim($product->original_product_name)
        : ((is_string($product->product_name) && trim($product->product_name) !== '')
          ? trim($product->product_name)
          : (string) $product->slug);

    $variationsCount = $product->relationLoaded('variations')
      ? $product->variations->count()
      : $product->variations()->count();

    $kind = match ($product->product_type) {
      'variable' => ($variationsCount <= 1 ? ProductKind::Simple : ProductKind::Variable),
      'set'      => ProductKind::Set,
      default    => ProductKind::Simple,
    };

    $properties = collect($extractor->extract($product) ?? [])
      ->filter(fn($v) => is_string($v) && trim($v) !== '')
      ->map(fn($v) => trim($v))
      ->unique()
      ->values()
      ->all();

    return new self(
      kind: $kind,
      manufacturerName: $manufacturerName,
      categoryName: '',
      designation: $designation,
      properties: $properties,
      manufacturerId: (int) $product->manufacturer_id,
    );
  }
}
