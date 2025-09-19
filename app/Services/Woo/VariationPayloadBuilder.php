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
 * (REST: /wp-json/wc/v3/products/{productId}/variations).
 *
 * Annahmen/Kontext:
 * - Preise werden im System als Integer (Cents) gespeichert und erst bei der Ausgabe formatiert.
 * - Häufige Varianteneigenschaften sind z.B. size, color, length, certification o.ä.
 * - Attribut-Namen für Woo lassen sich per config('woo.mapping.variation_attributes') übersteuern.
 * - Fallback-Werte (manage_stock, stock_status etc.) sind hier konservativ gesetzt und können
 *   später im Service/CLI oder via Config justiert werden.
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
   * - 'sku', 'ean', 'weight', 'regular_price', 'sale_price', 'stock_quantity'
   * - beliebige weitere Custom-Felder (werden ignoriert, wenn nicht vorhanden)
   *
   * Unterstützte Woo-Felder (Auszug):
   * - 'sku', 'regular_price', 'sale_price', 'manage_stock', 'stock_quantity',
   *   'stock_status', 'weight', 'dimensions', 'image', 'attributes', 'meta_data'
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
      // Preise werden als String mit Dezimalpunkt erwartet
      'regular_price'  => 'regular_price',
      'sale_price'     => 'sale_price',
      'stock_quantity' => 'stock_quantity',
      'weight'         => 'weight',
      // 'ean' könnte als meta_data abgebildet werden (siehe buildMetaData)
    ]);

    $this->attributeMap = config('woo.mapping.variation_attribute_map', [
      'size'          => 'Size',
      'color'         => 'Color',
      'length'        => 'Length',
      'certification' => 'Certification',
    ]);

    $this->defaults = config('woo.mapping.variation_defaults', [
      'manage_stock' => true,
      // Wenn stock_quantity null ist: setze "instock" statt "outofstock",
      // damit initial keine ungewollten Deaktivierungen passieren.
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
      'product_id'      => $product->id,
      'variations_count' => is_array($variations) ? count($variations) : $variations->count(),
    ]);

    return $payloads;
  }

  /**
   * Baut die Payload für genau eine Variante.
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

      // Preisfelder: Integer (Cents) -> String "12.34"
      if (in_array($wooKey, ['regular_price', 'sale_price'], true)) {
        $payload[$wooKey] = $this->formatPrice($value);
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
        'src'   => $variation->image_url,
        'name'  => $this->buildImageName($product, $variation),
        // 'alt' optional
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
   * Formatiert Integer-Cents zu Woo-Preisstring mit Punkt als Dezimaltrenner.
   *
   * @param  int|string $cents
   * @return string
   */
  protected function formatPrice(int|string $cents): string
  {
    $cents = (int) $cents;
    return number_format($cents / 100, 2, '.', '');
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

    foreach ($this->attributeMap as $internalKey => $wooName) {
      // interner Wert (z.B. $variation->size)
      $value = $variation->{$internalKey} ?? null;

      if ($value === null || $value === '') {
        continue;
      }

      $result[] = [
        'name'   => $wooName,
        'option' => (string) $value,
      ];
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
   * Hinweis: In Woo können Meta-Daten je nach Shop-Setup andre Namen erfordern.
   *          Per Config kann man hier später Mappings ergänzen.
   *
   * @param  ProductVariation $variation
   * @return array<int,array{key:string,value:mixed}>
   */
  protected function buildMetaData(ProductVariation $variation): array
  {
    $meta = [];

    if (!empty($variation->ean)) {
      $meta[] = [
        'key'   => 'ean',
        'value' => (string) $variation->ean,
      ];
    }

    // Beispiel für weitere Meta-Keys:
    // if (!empty($variation->barcode)) {
    //     $meta[] = ['key' => 'barcode', 'value' => $variation->barcode];
    // }

    return $meta;
  }
}
