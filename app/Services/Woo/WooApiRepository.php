<?php

namespace App\Services\Woo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Class WooApiRepository
 *
 * Implementiert die vom WooParentResolver und ProductExportOrchestrator
 * erwarteten Lookup-/Mutations-Methoden auf Basis von WooClient.
 *
 * Wichtige Punkte:
 * - SKU-first Matching (gemäß Kunden-Vorgabe).
 * - EAN/MPN nur als Fallback, wenn am Kandidaten keine SKU vorhanden ist.
 * - Variationssuche erfolgt parentbasiert (Woo REST listet Variationen nicht global nach SKU).
 *
 * @package App\Services\Woo
 */
class WooApiRepository implements WooRepositoryInterface
{
  public function __construct(
    protected WooClient $client
  ) {}

  // ---------------------------------------------------------
  //  Lookup-Methoden
  // ---------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function findBySku(string $sku): ?array
  {
    // 1) Produkte (Simple/Parent)
    $prods = $this->client->get('products', ['sku' => $sku, 'per_page' => 1]);
    if (is_array($prods) && !empty($prods)) {
      $item = $prods[0];
      Log::debug('WooApiRepository: product matched by SKU', ['sku' => $sku, 'id' => $item['id'] ?? null]);
      return $item;
    }

    // 2) Variationen (via Parents)
    $parents = $this->client->get('products', ['search' => $sku, 'per_page' => 50]);
    foreach ((array) $parents as $parent) {
      $type = $this->getItemType($parent);
      if ($type !== 'parent') {
        continue;
      }
      $foundVar = $this->findVariationBySkuUnderParent((int) $parent['id'], $sku);
      if ($foundVar) {
        Log::debug('WooApiRepository: variation matched by SKU under parent', [
          'sku' => $sku,
          'parent_id' => $parent['id'],
          'variation_id' => $foundVar['id'] ?? null,
        ]);
        return $foundVar;
      }
    }

    return null;
  }

  /**
   * {@inheritdoc}
   */
  public function findByEan(string $ean): ?array
  {
    $eanKey = (string) config('woo.mapping.meta_keys.ean', 'ean');

    $candidates = $this->client->get('products', ['search' => $ean, 'per_page' => 50]);
    foreach ((array) $candidates as $p) {
      if ($this->metaEquals($p, $eanKey, $ean) || $this->attrEquals($p, $ean)) {
        Log::debug('WooApiRepository: product matched by EAN', ['ean' => $ean, 'id' => $p['id'] ?? null]);
        return $p;
      }
      if ($this->getItemType($p) === 'parent') {
        $var = $this->findVariationByMetaUnderParent((int) $p['id'], $eanKey, $ean);
        if ($var) {
          Log::debug('WooApiRepository: variation matched by EAN', ['ean' => $ean, 'parent_id' => $p['id']]);
          return $var;
        }
      }
    }

    // Optionaler breiter Fallback (kann bei großen Katalogen teuer sein)
    $parents = $this->client->get('products', ['per_page' => 50]);
    foreach ((array) $parents as $p) {
      if ($this->getItemType($p) === 'parent') {
        $var = $this->findVariationByMetaUnderParent((int) $p['id'], $eanKey, $ean);
        if ($var) {
          return $var;
        }
      }
    }

    return null;
  }

  /**
   * {@inheritdoc}
   */
  public function findByMpn(string $mpn): ?array
  {
    $mpnKey = (string) config('woo.mapping.meta_keys.mpn', 'mpn');

    $candidates = $this->client->get('products', ['search' => $mpn, 'per_page' => 50]);
    foreach ((array) $candidates as $p) {
      if ($this->metaEquals($p, $mpnKey, $mpn) || $this->attrEquals($p, $mpn)) {
        Log::debug('WooApiRepository: product matched by MPN', ['mpn' => $mpn, 'id' => $p['id'] ?? null]);
        return $p;
      }
      if ($this->getItemType($p) === 'parent') {
        $var = $this->findVariationByMetaUnderParent((int) $p['id'], $mpnKey, $mpn);
        if ($var) {
          Log::debug('WooApiRepository: variation matched by MPN', ['mpn' => $mpn, 'parent_id' => $p['id']]);
          return $var;
        }
      }
    }

    return null;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemType(array $item): string
  {
    $type = (string) ($item['type'] ?? '');
    if ($type === 'variable') {
      return 'parent';
    }
    if (array_key_exists('parent_id', $item)) {
      return 'variation';
    }
    return 'simple';
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(array $item): int
  {
    return (int) ($item['id'] ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(array $item): ?int
  {
    $pid = Arr::get($item, 'parent_id');
    return is_null($pid) ? null : (int) $pid;
  }

  /**
   * {@inheritdoc}
   */
  public function findVariantUnderParentByAttributes(int $parentId, array $attributes): ?int
  {
    if (empty($attributes)) {
      return null;
    }

    // WICHTIG: Für Varianten immer Taxonomie-Slugs benutzen (z. B. pa_color, pa_size).
    // Falls nicht gesetzt, auf sinnvolle Defaults fallen.
    $tax = (array) config('woo.mapping.variation_attribute_taxonomies', [
      'color' => 'pa_color',
      'size'  => 'pa_size',
    ]);

    $expected = [];
    foreach ($attributes as $localKey => $val) {
      if ($val === null || $val === '') {
        continue;
      }
      // Name = Taxonomie-Slug (z. B. pa_color); Option = konkreter Wert (z. B. "Red" oder "L")
      $attrName = $tax[$localKey] ?? $localKey; // Fallback auf localKey, falls nicht gemappt
      $expected[] = ['name' => (string) $attrName, 'option' => (string) $val];
    }

    $page = 1;
    $per  = (int) config('woo.sync.variations.per_page', 100);
    while (true) {
      $vars = $this->client->get("products/{$parentId}/variations", ['per_page' => $per, 'page' => $page]);
      if (empty($vars)) {
        break;
      }
      foreach ($vars as $v) {
        $attrs = (array) ($v['attributes'] ?? []);
        if ($this->attributesMatch($attrs, $expected)) {
          return (int) ($v['id'] ?? 0);
        }
      }
      $page++;
    }

    return null;
  }


  // ---------------------------------------------------------
  //  Mutations
  // ---------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function updateProduct(int $productId, array $payload): ?array
  {
    return $this->client->put("products/{$productId}", $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function updateVariation(int $parentId, int $variationId, array $payload): ?array
  {
    return $this->client->put("products/{$parentId}/variations/{$variationId}", $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function createProduct(array $payload): ?array
  {
    return $this->client->post('products', $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function createVariation(int $parentId, array $payload): ?array
  {
    return $this->client->post("products/{$parentId}/variations", $payload);
  }

  // ---------------------------------------------------------
  //  Hilfsfunktionen
  // ---------------------------------------------------------

  protected function findVariationBySkuUnderParent(int $parentId, string $sku): ?array
  {
    $page = 1;
    $per  = (int) config('woo.sync.variations.per_page', 100);
    while (true) {
      $vars = $this->client->get("products/{$parentId}/variations", ['per_page' => $per, 'page' => $page]);
      if (empty($vars)) {
        break;
      }
      foreach ($vars as $v) {
        if ((string) ($v['sku'] ?? '') === $sku) {
          return $v;
        }
      }
      $page++;
    }
    return null;
  }

  protected function findVariationByMetaUnderParent(int $parentId, string $key, string $value): ?array
  {
    $page = 1;
    $per  = (int) config('woo.sync.variations.per_page', 100);
    while (true) {
      $vars = $this->client->get("products/{$parentId}/variations", ['per_page' => $per, 'page' => $page]);
      if (empty($vars)) {
        break;
      }
      foreach ($vars as $v) {
        if ($this->metaEquals($v, $key, $value)) {
          return $v;
        }
      }
      $page++;
    }
    return null;
  }

  protected function metaEquals(array $item, string $key, string $value): bool
  {
    $meta = (array) ($item['meta_data'] ?? []);
    foreach ($meta as $m) {
      $k = (string) Arr::get($m, 'key', '');
      $v = Arr::get($m, 'value');
      if ($k === $key && (string) $v === (string) $value) {
        return true;
      }
    }
    return false;
  }

  protected function attrEquals(array $product, string $needle): bool
  {
    $attrs = (array) ($product['attributes'] ?? []);
    foreach ($attrs as $a) {
      $options = (array) Arr::get($a, 'options', []);
      foreach ($options as $opt) {
        if ((string) $opt === (string) $needle) {
          return true;
        }
      }
    }
    return false;
  }

  protected function attributesMatch(array $have, array $expected): bool
  {
    $mapHave = [];
    foreach ($have as $h) {
      $n = (string) (Arr::get($h, 'name') ?? '');
      $o = (string) (Arr::get($h, 'option') ?? '');
      if ($n !== '' && $o !== '') {
        $mapHave[$n] = $o;
      }
    }

    foreach ($expected as $e) {
      $n = (string) (Arr::get($e, 'name') ?? '');
      $o = (string) (Arr::get($e, 'option') ?? '');
      if ($n === '' || $o === '') {
        continue;
      }
      if (!array_key_exists($n, $mapHave)) {
        return false;
      }
      if ((string) $mapHave[$n] !== (string) $o) {
        return false;
      }
    }

    return true;
  }
}
