<?php

namespace App\Services\Woo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Class WooApiRepository
 *
 * Zweck:
 * - Implementiert die vom WooParentResolver erwarteten Lookup-/Hilfs-Methoden
 *   mithilfe des vorhandenen WooClient (Low-Level HTTP).
 * - SKU ist Primärschlüssel für Matches; EAN/MPN nur Fallback, falls keine SKU am Kandidaten vorhanden.
 *
 * Annahmen / Hinweise:
 * - Woo REST-Suche:
 *   - /products?sku=... findet nur Simple/Parent, aber nicht direkt Variationen.
 *   - Variationen müssen unterhalb eines Parents paginiert abgefragt werden:
 *     /products/{parentId}/variations?page=...&per_page=...
 * - EAN/MPN liegen in deinem Shop wahrscheinlich als meta_data (Key konfigurierbar) oder als Attribut.
 *   Wir prüfen zuerst Produkte (incl. Parent), danach deren Variationen.
 *
 * Konfiguration (empfohlen in config/woo.php):
 *   'mapping' => [
 *     'meta_keys' => [
 *       'ean' => '_ean',          // Beispiel! an deinen Shop anpassen
 *       'gtin' => '_gtin',
 *       'mpn' => '_mpn',
 *     ],
 *     'variation_attribute_map' => [
 *       // lokale Attribut-Schlüssel => Woo-Attribut-Namen (z. B. ['size' => 'Size', 'color' => 'Color'])
 *     ],
 *   ],
 *
 * @package App\Services\Woo
 */
class WooApiRepository
{
  public function __construct(
    protected WooClient $client
  ) {}

  // ---------------------------------------------------------
  //  Lookup-Methoden (vom Resolver erwartet)
  // ---------------------------------------------------------

  /**
   * Sucht Produkt ODER Variation per exakter SKU.
   *
   * @param  string $sku
   * @return array|null  Roher Woo-API-Datensatz (Product oder Variation)
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

    // 2) Variationen (nur via Parent-Iteration) – Heuristik:
    //    Suche potentielle Parents via search=..., danach Variationen listen und exakte SKU matchen.
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
   * Sucht Produkt ODER Variation per EAN (Fallback, wenn SKU fehlt).
   * Hinweis: Woo bietet kein generisches "meta query" in REST, daher:
   *  - Kandidaten via /products?search=... holen, dann meta_data prüfen.
   *  - Variationen des Parents durchgehen und deren meta_data prüfen.
   *
   * @param  string $ean
   * @return array|null
   */
  public function findByEan(string $ean): ?array
  {
    $eanKey = (string) config('woo.mapping.meta_keys.ean', 'ean');

    // 1) Produkte prüfen
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

    // 2) Fallback: breiter suchen (nur selten, Performance!)
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
   * Sucht Produkt ODER Variation per MPN (Fallback, wenn SKU fehlt).
   *
   * @param  string $mpn
   * @return array|null
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
   * Liefert 'parent' | 'simple' | 'variation' für ein Woo-Item.
   */
  public function getItemType(array $item): string
  {
    $type = (string) ($item['type'] ?? '');
    if ($type === 'variable') {
      return 'parent';
    }
    if (array_key_exists('parent_id', $item)) {
      // Variation-Objekte haben parent_id in der API
      return 'variation';
    }
    return 'simple';
  }

  /**
   * Liefert die ID eines Items (Product/Variation).
   */
  public function getItemId(array $item): int
  {
    return (int) ($item['id'] ?? 0);
  }

  /**
   * Liefert die Parent-ID, wenn Variation – sonst null.
   */
  public function getParentId(array $item): ?int
  {
    $pid = Arr::get($item, 'parent_id');
    return is_null($pid) ? null : (int) $pid;
  }

  /**
   * Sucht unterhalb eines Parent-Produkts eine Variation, deren Attribute exakt passen.
   *
   * @param  int   $parentId
   * @param  array $attributes  ['size' => 'L', 'color' => 'RED', ...]
   * @return int|null           Variation-ID
   */
  public function findVariantUnderParentByAttributes(int $parentId, array $attributes): ?int
  {
    if (empty($attributes)) {
      return null;
    }

    // Map lokale Attribute → erwartete Woo-Namen (z. B. 'Size', 'Color')
    $attrMap = (array) config('woo.mapping.variation_attribute_map', []);
    $expected = [];
    foreach ($attributes as $localKey => $val) {
      $wooName = $attrMap[$localKey] ?? $localKey;
      if ($val !== null && $val !== '') {
        $expected[] = ['name' => (string) $wooName, 'option' => (string) $val];
      }
    }

    // Variationen seitenweise laden und vergleichen
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
  //  Mutations (vom Orchestrator genutzt)
  //  → hier nur als Platzhalter, falls du sie zentralisieren willst.
  //  Dein ProductUpsertService/VariationSyncService deckt Create/Update bereits ab.
  // ---------------------------------------------------------

  public function updateProduct(int $productId, array $payload): ?array
  {
    return $this->client->put("products/{$productId}", $payload);
  }

  public function updateVariation(int $parentId, int $variationId, array $payload): ?array
  {
    return $this->client->put("products/{$parentId}/variations/{$variationId}", $payload);
  }

  public function createProduct(array $payload): ?array
  {
    return $this->client->post('products', $payload);
  }

  public function createVariation(int $parentId, array $payload): ?array
  {
    return $this->client->post("products/{$parentId}/variations", $payload);
  }

  // ---------------------------------------------------------
  //  Hilfsfunktionen
  // ---------------------------------------------------------

  /**
   * Findet Variation per exakter SKU unter einem Parent.
   */
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

  /**
   * Findet Variation per meta_data-Key/Wert unter einem Parent.
   */
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

  /**
   * Vergleicht meta_data eines Items mit Key/Wert (exakter String-Vergleich).
   */
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

  /**
   * Prüft, ob eines der Produkt-Attribute (Global Attribute) dem gesuchten Wert entspricht.
   * (z. B. wenn EAN/MPN als globales Attribut gepflegt wurde)
   */
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

  /**
   * Vergleicht Variation-Attribute: ['name' => 'Size', 'option' => 'M'] usw.
   */
  protected function attributesMatch(array $have, array $expected): bool
  {
    // Normalisiere in Maps: name=>option
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
