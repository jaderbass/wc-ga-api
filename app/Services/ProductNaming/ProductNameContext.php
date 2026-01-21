<?php

namespace App\Services\ProductNaming;

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
    public readonly array $properties = [],
    public readonly ?int $manufacturerId = null,
  ) {}
}
