<?php

namespace App\Services\Woo;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Class ProductExportOrchestrator
 *
 * Orchestriert den Export von Produkten/Varianten nach WooCommerce.
 * Nutzt WooParentResolver für die Auflösung bestehender Produkte
 * und ProductUpsertService für die Anlage/Aktualisierung.
 */
class ProductExportOrchestrator
{
  /** @var WooApiRepository */
  protected WooApiRepository $repo;

  /**
   * Konstruktor
   *
   * @param WooParentResolver    $resolver
   * @param ProductUpsertService $upsert
   * @param WooApiRepository     $repo
   */
  public function __construct(
    protected WooParentResolver $resolver,
    protected ProductUpsertService $upsert,
    WooApiRepository $repo,
  ) {
    $this->repo = $repo;
  }

  /**
   * Exportiert ein einzelnes Produkt oder eine Variation.
   *
   * @param Shop  $shop
   * @param array $candidate
   * @return void
   */
  public function exportOne(Shop $shop, array $candidate): void
  {
    $resolved = $this->resolver->resolve($shop, $candidate);
    if (is_array($resolved)) {
      $resolved = ResolvedTarget::fromArray($resolved);
    }

    if ($resolved->conflict) {
      Log::error('Duplicate identifier, manual review required', [
        'shopId' => $shop->id,
        'sku'    => $candidate['sku'] ?? null,
        'match'  => $resolved->match_type,
      ]);
      return;
    }

    $this->upsert->upsert(
      $shop->id,
      $candidate,
      $resolved->woo_product_id,
      $resolved->woo_variation_id
    );
  }

  /**
   * Führt den Export/Upsert für ein lokales Produkt aus.
   *
   * @param Shop  $shop
   * @param array $localProduct
   * @return void
   */
  public function handle(Shop $shop, array $localProduct): void
  {
    $type        = $localProduct['type'] ?? 'simple';
    $isVariation = ($type === 'variation');
    $isParent    = ($type === 'variable');

    if ($isVariation && empty($localProduct['sku'])) {
      Log::warning('Skip variation: missing SKU', [
        'shopId' => $shop->id,
        'id'     => $localProduct['id'] ?? null,
        'name'   => $localProduct['product_name'] ?? null,
      ]);
      return;
    }

    if ($isParent && !empty($localProduct['sku'])) {
      Log::warning('Parent has SKU set (should be empty in Woo)', [
        'shopId' => $shop->id,
        'sku'    => $localProduct['sku'],
        'name'   => $localProduct['product_name'] ?? null,
      ]);
    }

    $resolved = $this->resolver->resolve($shop, $localProduct);
    if (is_array($resolved)) {
      $resolved = ResolvedTarget::fromArray($resolved);
    }

    if ($resolved->conflict) {
      Log::error('Duplicate identifier, manual review required', [
        'shopId' => $shop->id,
        'sku'    => $localProduct['sku'] ?? null,
        'match'  => $resolved->match_type,
      ]);
      return;
    }

    $this->upsert->upsert(
      $shop->id,
      $localProduct,
      $resolved->woo_product_id,
      $resolved->woo_variation_id
    );
  }

  /**
   * Gibt das WooApiRepository zurück.
   *
   * @return WooApiRepository
   */
  protected function safeRepo(): WooApiRepository
  {
    return $this->repo;
  }

  /**
   * Versucht die Attribut-ID über den Slug zu ermitteln.
   *
   * @param Shop   $shop
   * @param string $slug
   * @return int|null
   */
  protected function getAttributeIdBySlugIfPossible(Shop $shop, string $slug): ?int
  {
    $repo = $this->safeRepo();
    if (method_exists($repo, 'getAttributeIdBySlug')) {
      return $repo->getAttributeIdBySlug($shop->id, $slug);
    }
    return null;
  }
}
