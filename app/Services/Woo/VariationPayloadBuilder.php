<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * VariationPayloadBuilder
 *
 * Baut WooCommerce-konforme Payload-Arrays für Produkt-Varianten
 * (REST: /wp-json/wc/v3/products/{productId}/variations), OHNE Preisfelder.
 *
 * Annahmen/Kontext:
 * - Preise werden im Projekt bewusst NICHT synchronisiert.
 * - Häufige Varianteneigenschaften sind z.B. size, color, length, certification.
 * - Attribut-Namen für Woo lassen sich per config('woo.mapping.variation_attributes') übersteuern.
 * - Fallback-Werte (manage_stock, stock_status etc.) sind konservativ gesetzt und können
 *   via Config justiert werden.
 *
 * Erweiterbarkeit:
 * - Mapping der Varianten-Felder -> Woo-Felder via $fieldMap (konfigurierbar).
 * - Attribut-Zuordnung via $attributeMap (konfigurierbar).
 *
 * @author  JAderBass
 * @since   2025-09-19
 */
class VariationPayloadBuilder
{
  /**
   * @var array<string, string> Feld-Mapping von internen Variations-Feldern zu Woo-Feldern
   *
   * Unterstützte Keys auf unserer Seite (Beispiele):
   * - 'sku', 'ean', 'weight', 'stock_quantity'
   *
   * Unterstützte Woo-Felder (Auszug):
   * - 'sku', 'manage_stock', 'stock_quantity', 'stock_status',
   *   'weight', 'dimensions', 'image', 'attributes', 'meta_data'
   */
  protected array $fieldMap;

  /**
   * @var array<string, string> Attribut-Mapping: internes Attribut -> Woo Attributname
   *
   * Beispiel:
   * [
   *   'size'  => 'Size',
   *   'color' => 'Color',
   *   'length'=> 'Length'
   * ]
   */
  protected array $attributeMap;

  /**
   * @var array<string, mixed> Defaultwerte für Woo-Felder
   */
  protected array $defaults;

  /**
   * Konstruktor lädt optionale Konfigurationen.
   *
   * - woo.mapping.variation_field_map
   * - woo.mapping.variation_attribute_map
   * - woo.mapping.variation_defaults
   */
  public function __construct()
  {
    $this->fieldMap = config('woo.mapping.variation_field_map', [
      'sku'            => 'sku',
      'stock_quantity' => 'stock_quantity',
      'weight'         => 'weight',
      // 'ean' wird als meta_data abgebildet (siehe buildMetaData)
    ]);

    $this->attributeMap = config('woo.mapping.variation_attribute_map', [
      'size'          => 'Size',
      'color'         => 'Color',
      'length'        => 'Length',
      'certification' => 'Certification',
    ]);

    $this->defaults = config('woo.mapping.variation_defaults', [
      'manage_stock' => true,
      // Bei fehlender Menge standardmäßig "instock", um versehentliche Deaktivierungen zu vermeiden.
      'stock_status' => 'instock',
    ]);
  }

  /**
   * Baut die Payload für alle übergebenen Varianten eines Produkts.
   *
   * @param  Product               $product
   * @param  Collection<int,ProductVariation>|array<int,ProductVariation> $variations
   * @return array<int,array<string,mixed>>
   */
  public function buildForCollection(Product $product, Collection|array $variations): array
  {
    $payloads = [];

    foreach ($variations as $variation) {
      $payload = $this->buildForVariation($product, $variation);
      $payloads[] = $payload;
    }

    Log::debug('VariationPayloadBuilder: collection payload built', [
      'product_id'       => $product->id,
      'variations_count' => is_array($variations) ? count($variations) : $variations->count(),
    ]);

    return $payloads;
  }

  /**
   * Baut die Payload für genau eine Variante (ohne Preisfelder).
   *
   * @param  Product          $product
   * @param  ProductVariation $variation
   * @return array<string,mixed>
   */
  public function buildForVariation(Product $product, ProductVariation $variation): array
  {
    $payload = [];

    // 1) Defaults
    foreach ($this->defaults as $key => $value) {
      $payload[$key] = $value;
    }

    // 2) Direkte Feldzuordnung laut $fieldMap
    foreach ($this->fieldMap as $internal => $wooKey) {
      $value = $variation->{$internal} ?? null;

      if ($value === null) {
        continue;
      }

      // Gewicht als String (Woo erwartet String)
      if ($wooKey === 'weight') {
        $payload[$wooKey] = (string) $value;
        continue;
      }

      $payload[$wooKey] = $value;
    }

    // 3) Stock-Status ableiten, wenn manage_stock aktiv ist
    if (($payload['manage_stock'] ?? false) === true) {
      $qty = $payload['stock_quantity'] ?? null;
      if (is_numeric($qty)) {
        $payload['stock_status'] = ((int) $qty) > 0 ? 'instock' : 'outofstock';
      }
    }

    // 4) Attribute (z.B. size/color) -> Woo-Attributstruktur
    $payload['attributes'] = $this->buildAttributes($variation);

    // 5) optionale Bild-Zuordnung, falls vorhanden (z.B. $variation->image_url)
    if (!empty($variation->image_url)) {
      $payload['image'] = [
        'src'  => $variation->image_url,
        'name' => $this->buildImageName($product, $variation),
      ];
    }

    // 6) Meta-Daten (z.B. EAN)
    $meta = $this->buildMetaData($variation);
    if (!empty($meta)) {
      $payload['meta_data'] = $meta;
    }

    Log::debug('VariationPayloadBuilder: single payload built', [
      'product_id'   => $product->id,
      'variation_id' => $variation->id,
      'sku'          => $payload['sku'] ?? null,
      'attributes'   => $payload['attributes'] ?? [],
    ]);

    return $payload;
  }

  /**
   * Baut die Attribute-Struktur für Woo aus den internen Variations-Werten.
   *
   * @param  ProductVariation $variation
   * @return array<int,array{name:string,option:string}>
   */
  protected function buildAttributes(ProductVariation $variation): array
  {
    $result = [];

    // Shop holen für Resolver (einmal pro Request ok)
    $shop = \App\Models\Shop::query()->first();
    $resolver = new \App\Services\Woo\WooAttributeResolver($shop);

    foreach ($this->attributeMap as $internalKey => $wooName) {
      $value = $variation->{$internalKey} ?? null;
      if ($value === null || $value === '') continue;

      // Lokalen Key → Woo-Attribut ermitteln (per ID)
      $res = $resolver->resolve($internalKey);
      if ($res) {
        // Globale (Taxonomie-)Attribute: per ID + option (Term-Name)
        $result[] = [
          'id'     => $res['id'],
          'option' => (string)$value,   // Muss exakt zum Term-Namen passen!
        ];
      } else {
        // Fallback: freies Attribut (nicht ideal für Varianten, aber besser als leer)
        $result[] = [
          'name'   => $wooName,
          'option' => (string)$value,
        ];
      }
    }

    if (empty($result)) {
      Log::warning('VariationPayloadBuilder: no attributes for variation', [
        'variation_id' => $variation->id,
        'present' => array_filter($variation->toArray(), fn($v) => $v !== null && $v !== ''),
      ]);
    }

    return $result;
  }


  /**
   * Erzeugt einen (optionalen) Bildnamen für die Variante.
   *
   * @param  Product          $product
   * @param  ProductVariation $variation
   * @return string
   */
  protected function buildImageName(Product $product, ProductVariation $variation): string
  {
    $base = $product->slug ?? Str::slug($product->product_name ?? 'product');
    $sku  = $variation->sku ?? ('var-' . $variation->id);
    return $base . '-' . $sku;
  }

  /**
   * Erzeugt Meta-Daten für Woo (z.B. EAN).
   *
   * @param  ProductVariation $variation
   * @return array<int,array{key:string,value:mixed}>
   */
  protected function buildMetaData(ProductVariation $variation): array
  {
    $meta = [];

    if (!empty($variation->ean)) {
      $meta[] = ['key' => 'ean', 'value' => (string) $variation->ean];
    }

    return $meta;
  }
}
