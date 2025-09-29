<?php

namespace App\Services\Woo;

use Illuminate\Support\Facades\Log;

/**
 * Class ProductUpsertService
 *
 * Kümmert sich um den eigentlichen Upsert-Prozess
 * eines Produkts oder einer Variation in WooCommerce.
 */
class ProductUpsertService
{
  /**
   * Führt einen Upsert in WooCommerce aus.
   *
   * @param int        $shopId
   * @param array      $product
   * @param int|null   $targetProductId
   * @param int|null   $targetVariationId
   * @return void
   */
  public function upsert(int $shopId, array $product, ?int $targetProductId = null, ?int $targetVariationId = null): void
  {
    Log::info('Upsert called', [
      'shopId'           => $shopId,
      'sku'              => $product['sku'] ?? null,
      'targetProductId'  => $targetProductId,
      'targetVariationId' => $targetVariationId,
    ]);

    // 👉 Hier deine bestehende Logik einfügen,
    // die mit WooApiRepository arbeitet und
    // Create/Update-Requests absetzt.
  }
}
