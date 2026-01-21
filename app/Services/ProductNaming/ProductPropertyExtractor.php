<?php

namespace App\Services\ProductNaming;

use App\Models\Product;
use App\Models\ProductAttributeValue;

/**
 * Extracts customer-relevant product properties (Eigenschaften)
 * from stored product attribute values.
 *
 * Responsibility:
 * - Reads attribute values from DB models
 * - Maps them into a fixed, ordered list (p1, p2, p3)
 * - Does NOT build names
 * - Does NOT format strings
 *
 * This keeps ProductNameBuilder simple and deterministic.
 */
final class ProductPropertyExtractor
{
  /**
   * Attribute slug order as required by the customer.
   *
   * IMPORTANT:
   * - Order matters!
   * - Only these attributes are allowed to influence the product name.
   *
   * Example:
   *  p1 = size
   *  p2 = length
   *  p3 = color
   */
  private const PROPERTY_SLUG_ORDER = [
    'size',
    'length',
    'color',
  ];

  /**
   * Builds the ordered property list for product naming.
   *
   * @return array<int, string|null>  [p1, p2, p3]
   */
  public function extract(Product $product): array
  {
    /**
     * Collect all attribute values related to this product.
     *
     * NOTE:
     * We intentionally use attribute values on PRODUCT level,
     * not variation level.
     */
    $values = $this->collectAttributeValues($product);

    $result = [];

    foreach (self::PROPERTY_SLUG_ORDER as $slug) {
      $result[] = $values[$slug] ?? null;
    }

    return $result;
  }

  /**
   * Collects attribute values indexed by attribute slug.
   *
   * Result example:
   * [
   *   'size'   => '0.5',
   *   'length' => '90 mm',
   *   'color'  => 'red',
   * ]
   *
   * @return array<string, string>
   */
  private function collectAttributeValues(Product $product): array
  {
    $map = [];

    /**
     * You may already have this relation on Product.
     * If not, we resolve via variations.
     */
    $product->loadMissing('variations.attributeValues.attribute');

    foreach ($product->variations as $variation) {
      foreach ($variation->attributeValues as $value) {
        $slug = $value->attribute->slug ?? null;

        if ($slug === null) {
          continue;
        }

        /**
         * First value wins.
         * We do NOT override to keep naming deterministic.
         */
        if (! array_key_exists($slug, $map)) {
          $map[$slug] = $this->normalizeValue($value);
        }
      }
    }

    // Fallback: if no attribute values are attached (pivot not used),
    // read values from attributes_json stored on the variation.
    if ($map === []) {
      foreach ($product->variations as $variation) {
        $json = $variation->attributes_json ?? null;

        if (!is_array($json) || $json === []) {
          continue;
        }

        foreach ($json as $k => $v) {
          if (!is_string($k)) {
            continue;
          }

          $val = is_string($v) ? trim($v) : (is_numeric($v) ? (string) $v : null);
          if ($val === null || $val === '') {
            continue;
          }

          // Example key: "Attribute Group: Schlingenlänge | Farbe"
          $key = trim($k);

          // Map customer properties by detecting known keywords in the key.
          // Adjust these mappings to your real attribute groups.
          if (!isset($map['length']) && preg_match('/schlingenlänge|länge/i', $key)) {
            $map['length'] = $val;
            continue;
          }

          if (!isset($map['color']) && preg_match('/farbe|color/i', $key)) {
            $map['color'] = $val;
            continue;
          }

          if (!isset($map['size']) && preg_match('/größe|size/i', $key)) {
            $map['size'] = $val;
            continue;
          }
        }
      }
    }


    return $map;
  }

  /**
   * Normalizes a single attribute value for name usage.
   *
   * Example:
   *  value: "90"
   *  slug:  "length"
   *  => "90 mm" (if you later want unit logic)
   */
  private function normalizeValue(ProductAttributeValue $value): string
  {
    return trim($value->value);
  }
}
