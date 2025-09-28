<?php

namespace App\Services\Woo;

use App\Models\Shop;
use Illuminate\Support\Arr;

/**
 * Class WooParentResolver
 *
 * Löst Produkte/Varianten anhand von SKU/EAN/MPN
 * gegen den WooCommerce-Bestand auf.
 */
class WooParentResolver
{
  public function __construct(
    protected IdentifierStrategy $ids,
    protected WooApiRepository $repo,
    protected WooLinkStore $links,
  ) {}

  /**
   * Versucht das Produkt im Woo-Shop zu finden.
   *
   * @param Shop  $shop
   * @param array $product
   * @return ResolvedTarget
   */
  public function resolve(Shop $shop, array $product): ResolvedTarget
  {
    $shopId       = $shop->id;
    $localSkuNorm = $this->ids->normalizeSku(Arr::get($product, 'sku'));
    $eanNorm      = $this->ids->normalizeEan(Arr::get($product, 'ean'));
    $mpnNorm      = $this->ids->normalizeMpn(Arr::get($product, 'mpn'));

    $hit = $localSkuNorm ? $this->links->findByLocalSku($shopId, $localSkuNorm) : null;
    if ($hit && ($hit->woo_product_id || $hit->woo_variation_id)) {
      return ResolvedTarget::fromArray([
        'woo_product_id'   => $hit->woo_product_id,
        'woo_variation_id' => $hit->woo_variation_id,
        'match_type'       => 'CACHE_LOCAL_SKU',
        'confidence'       => 100,
      ]);
    }

    $knownWooSku = $hit?->woo_sku;
    $candidates  = $this->ids->buildCandidates($localSkuNorm, $eanNorm, $mpnNorm, $knownWooSku);

    foreach ($candidates as $cand) {
      if ($cand['type'] !== 'WOO_SKU') {
        continue;
      }
      $res = $this->safeFindBySku($shopId, $cand['value']);
      if ($this->isConflict($res)) {
        return ResolvedTarget::conflict('WOO_SKU', $cand['value']);
      }
      if ($this->isFound($res)) {
        return ResolvedTarget::fromArray([
          'woo_product_id'   => $this->productIdOf($res),
          'woo_variation_id' => $this->variationIdOf($res),
          'match_type'       => 'WOO_SKU',
          'confidence'       => $cand['confidence'],
        ]);
      }
    }

    return ResolvedTarget::notFound();
  }

  protected function safeFindBySku(int $shopId, string $sku)
  {
    return method_exists($this->repo, 'findBySku')
      ? $this->repo->findBySku($shopId, $sku)
      : null;
  }

  protected function safeFindByMeta(int $shopId, string $type, string $value)
  {
    return method_exists($this->repo, 'findByMeta')
      ? $this->repo->findByMeta($shopId, $type, $value)
      : null;
  }

  protected function isFound($res): bool
  {
    if (is_object($res) && method_exists($res, 'found')) {
      return $res->found();
    }
    if (is_array($res) && isset($res['found'])) {
      return (bool) $res['found'];
    }
    return (bool) $res;
  }

  protected function isConflict($res): bool
  {
    if (is_object($res) && method_exists($res, 'isConflict')) {
      return $res->isConflict();
    }
    if (is_array($res) && isset($res['conflict'])) {
      return (bool) $res['conflict'];
    }
    return false;
  }

  protected function productIdOf($res): ?int
  {
    if (is_object($res) && method_exists($res, 'targetProductId')) {
      return $res->targetProductId();
    }
    if (is_array($res) && isset($res['product_id'])) {
      return (int) $res['product_id'];
    }
    return null;
  }

  protected function variationIdOf($res): ?int
  {
    if (is_object($res) && method_exists($res, 'targetVariationId')) {
      return $res->targetVariationId();
    }
    if (is_array($res) && isset($res['variation_id'])) {
      return (int) $res['variation_id'];
    }
    return null;
  }
}
