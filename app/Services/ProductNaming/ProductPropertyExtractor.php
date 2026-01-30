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
   */
  private const PROPERTY_SLUG_ORDER = [
    'p1',
    'p2',
    'p3',
  ];

  /**
   * Builds the ordered property list for product naming.
   *
   * @return array<int, string|null>  [p1, p2, p3]
   */
  public function extract(Product $product): array
  {
    // ✅ Variable Produkte: genau 1 Eigenschaft aus Variation-Attributen (nur value)
    if ($product->product_type === 'variable') {
      $value = $this->pickVariablePropertyValueFromVariations($product);

      return $value !== null ? [$value] : [];
    }

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

  private function pickVariablePropertyValueFromVariations(Product $product): ?string
  {
    // N+1 vermeiden
    $product->loadMissing(['variations.attributeValues.attribute']);

    /** @var array<string, array<string, true>> $byAttributeName */
    $byAttributeName = [];

    foreach ($product->variations as $variation) {
      foreach ($variation->attributeValues as $attrValue) {
        $attrName = $attrValue->attribute?->name;
        if (!is_string($attrName) || trim($attrName) === '') {
          continue;
        }

        $value = trim((string) $attrValue->value);
        if ($value === '') {
          continue;
        }

        $byAttributeName[$attrName][$value] = true; // unique set
      }
    }

    if ($byAttributeName === []) {
      return null;
    }

    // Werte-Listen bauen (stabil sortiert)
    $lists = [];
    foreach ($byAttributeName as $name => $set) {
      $values = array_keys($set);
      sort($values, SORT_NATURAL | SORT_FLAG_CASE);
      $lists[$name] = $values;
    }

    // Priorität: Länge > Durchmesser > Größe > Farbe
    foreach (['Länge', 'Durchmesser', 'Größe', 'Farbe'] as $preferred) {
      if (!empty($lists[$preferred])) {
        return $lists[$preferred][0] ?? null;
      }
    }

    // Fallback: Attribut mit den meisten Ausprägungen
    uasort($lists, fn(array $a, array $b) => count($b) <=> count($a));
    $firstKey = array_key_first($lists);

    return $firstKey !== null ? ($lists[$firstKey][0] ?? null) : null;
  }


  /**
   * Collects naming properties (p1..p3) from either:
   * - variation attributeValues (pivot), or
   * - variation attributes_json (Aliens)
   *
   * @return array<string, string>  keys: p1|p2|p3
   */
  private function collectAttributeValues(Product $product): array
  {
    $map = [];

    // Avoid DB calls in unit tests if relations are already provided in-memory.
    if (! $product->relationLoaded('variations')) {
      $product->loadMissing('variations.attributeValues.attribute');
    }

    // 1) Preferred: pivot-based attribute values
    foreach ($product->variations as $variation) {
      foreach ($variation->attributeValues as $value) {
        $attrSlug = $value->attribute->slug ?? null;
        if (!is_string($attrSlug) || $attrSlug === '') {
          continue;
        }

        $normalized = trim($value->value);
        if ($normalized === '') {
          continue;
        }

        // Map known attribute slugs into our priority list
        $candidate = $this->candidateFromSlug($attrSlug, $normalized);
        if ($candidate === null) {
          continue;
        }

        $this->pushCandidate($map, $candidate['priority'], $candidate['value']);
      }
    }

    if ($map !== []) {
      return $this->toSlots($map);
    }

    // 2) Fallback: attributes_json on variation (Aliens)
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

        $key = trim($k);

        $candidate = $this->candidateFromAliensKey($key, $val);
        if ($candidate === null) {
          continue;
        }

        $this->pushCandidate($map, $candidate['priority'], $candidate['value']);
      }
    }

    return $this->toSlots($map);
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

  /**
   * Turns a map of priority => value into p1..p3 slots.
   *
   * @param array<int, string> $byPriority
   * @return array<string, string>
   */
  private function toSlots(array $byPriority): array
  {
    if ($byPriority === []) {
      return [];
    }

    ksort($byPriority); // priority 10 before 20 before 30 ...

    $values = array_values($byPriority);
    $values = array_slice($values, 0, 3);

    $slots = [];
    foreach ($values as $i => $v) {
      $slots['p' . ($i + 1)] = $v;
    }

    return $slots;
  }

  /**
   * Adds a candidate if the given priority is still free.
   *
   * @param array<int, string> $byPriority
   */
  private function pushCandidate(array &$byPriority, int $priority, string $value): void
  {
    if ($value === '') {
      return;
    }

    // first wins per priority (deterministic)
    if (!isset($byPriority[$priority])) {
      $byPriority[$priority] = $value;
    }
  }

  /**
   * Maps a generic attribute slug to a naming candidate.
   *
   * @return array{priority:int, value:string}|null
   */
  private function candidateFromSlug(string $slug, string $value): ?array
  {
    $s = mb_strtolower(trim($slug));

    // Priority list (lower = more important)
    if (preg_match('/\b(size|groesse|größe)\b/u', $s)) {
      return ['priority' => 10, 'value' => $value];
    }

    if (preg_match('/\b(version)\b/u', $s)) {
      return ['priority' => 20, 'value' => $value];
    }

    if (preg_match('/\b(length|laenge|länge)\b/u', $s)) {
      return ['priority' => 30, 'value' => $value];
    }

    if (preg_match('/\b(color|farbe)\b/u', $s)) {
      return ['priority' => 40, 'value' => $value];
    }

    return null;
  }

  /**
   * Maps an Aliens attributes_json key to a naming candidate.
   *
   * Example keys:
   * - "Attribute Group: Seil-Version"
   * - "Attribute Group: Seillänge"
   * - "Attribute Group: Seilfarbe"
   * - "Attribute Group: Größe | Farbe"
   *
   * @return array{priority:int, value:string}|null
   */
  private function candidateFromAliensKey(string $key, string $value): ?array
  {
    $k = mb_strtolower($key);

    // Größe / Size
    if (preg_match('/\b(größe|groesse|size)\b/u', $k)) {
      return ['priority' => 10, 'value' => $value];
    }

    // Version
    if (preg_match('/\bversion\b/u', $k)) {
      return ['priority' => 20, 'value' => $value];
    }

    // Länge (Seillänge, Länge, Length)
    if (preg_match('/\b(seil)?(länge|laenge|length)\b/u', $k)) {
      return ['priority' => 30, 'value' => $value];
    }

    // Farbe / Color
    if (preg_match('/\b(seil)?farbe\b/u', $k) || preg_match('/\bcolor\b/u', $k)) {
      return ['priority' => 40, 'value' => $value];
    }

    return null;
  }
}
