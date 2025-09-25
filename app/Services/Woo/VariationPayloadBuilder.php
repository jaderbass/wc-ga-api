<?php

namespace App\Services\Woo;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use function config;

/**
 * VariationPayloadBuilder
 *
 * Baut Woo-REST-Payloads für Varianten (POST/PUT /products/{productId}/variations)
 * ohne Preise, mit:
 *  - SKU aus product_variations.sku (Pflicht)
 *  - EAN/GTIN/MPN als meta_data auf Variations-Ebene (Keys via config)
 *  - Attribut-Mapping (size/color/...) gem. config('woo.mapping.variation_attribute_map')
 *  - Standard-Flags (manage_stock, stock_status) aus config('woo.mapping.variation_defaults')
 *
 * Hinweis zum EAN/GTIN:
 *  - In Woo-CSV erscheint "Attribute Value (pa_ean)" nur für PRODUKT-Attribute.
 *    Wir setzen das am Parent bereits über den ProductExportOrchestrator (globales Attribut).
 *  - Varianten-spezifische EAN/GTIN werden i. d. R. als meta_data auf der Variation geführt.
 *    (Viele EAN-Plugins in Woo lesen genau diese Meta-Keys aus.)
 *
 * Konfiguration (Auszug siehe config/woo.php):
 *  - mapping.identifiers.variation: { sku, mpn, ean, gtin }
 *  - mapping.meta_keys: { mpn, ean, gtin }
 *  - mapping.variation_attribute_map: z.B. ['size' => 'Size', 'color' => 'Color']
 *  - mapping.variation_field_map: Standardfelder z. B. { 'sku' => 'sku', 'stock_quantity' => 'stock_quantity', 'weight' => 'weight' }
 *  - mapping.variation_defaults: { manage_stock: true, stock_status: 'instock' }
 *
 * Erwartete Model-Struktur:
 *  - $product->variations(): Relation -> jede Variation als Eloquent Model
 *  - Variationsfelder nach deinen Spalten (siehe Config-Mappings)
 *
 * @author  JAderBass
 * @since   2025-09-25
 */
class VariationPayloadBuilder
{
  /**
   * Baut Payloads für alle Varianten eines Parent-Produkts.
   *
   * @param  Product $product
   * @return array<int, array<string,mixed>>  Liste von Woo-Variations-Payloads
   */
  public function buildForProduct(Product $product): array
  {
    if (!method_exists($product, 'variations')) {
      Log::warning('VariationPayloadBuilder: product has no variations() relation', ['product_id' => $product->getKey()]);
      return [];
    }

    $payloads = [];
    $product->variations()->orderBy('id')->chunk(200, function ($chunk) use (&$payloads) {
      foreach ($chunk as $variation) {
        $p = $this->buildForSingleVariation($variation);
        if ($p !== null) {
          $payloads[] = $p;
        }
      }
    });

    return $payloads;
  }

  /**
   * Baut den Payload für genau eine Variation.
   *
   * @param  Model $variation
   * @return array<string,mixed>|null  null, wenn keine SKU gesetzt ist
   */
  public function buildForSingleVariation(Model $variation): ?array
  {
    // ---- Mappings & Defaults -------------------------------------------------
    $ident      = (array) config('woo.mapping.identifiers.variation', []);
    $fieldMap   = (array) config('woo.mapping.variation_field_map', []);
    $attrMap    = (array) config('woo.mapping.variation_attribute_map', []);
    $metaKeys   = (array) config('woo.mapping.meta_keys', []);
    $defaults   = (array) config('woo.mapping.variation_defaults', []);

    $colSku     = $ident['sku']  ?? 'sku';
    $colMpn     = $ident['mpn']  ?? 'product_number';
    $colEan     = $ident['ean']  ?? 'ean';
    $colGtin    = $ident['gtin'] ?? 'gtin';

    $sku        = (string) ($variation->getAttribute($colSku) ?? '');
    if ($sku === '') {
      // Ohne SKU keine Variation in Woo
      Log::warning('VariationPayloadBuilder: variation skipped due to empty SKU', [
        'variation_id' => $variation->getAttribute('id'),
      ]);
      return null;
    }

    // ---- Basisfelder (ohne Preis) -------------------------------------------
    $payload = [
      'sku' => $sku,
    ];

    // manage_stock / stock_status Defaults
    if (array_key_exists('manage_stock', $defaults)) {
      $payload['manage_stock'] = (bool) $defaults['manage_stock'];
    }
    if (array_key_exists('stock_status', $defaults)) {
      $payload['stock_status'] = (string) $defaults['stock_status'];
    }

    // Mappe optionale Standardfelder aus variation_field_map (falls vorhanden)
    // z. B. stock_quantity, weight
    foreach ($fieldMap as $wooKey => $localCol) {
      // SKU behandeln wir separat (oben), daher hier überspringen
      if ($wooKey === 'sku') {
        continue;
      }
      $val = $variation->getAttribute($localCol);
      if (!is_null($val) && $val !== '') {
        // Gewicht als String liefern (Woo erwartet String)
        if ($wooKey === 'weight') {
          $payload[$wooKey] = (string) $val;
        } else {
          $payload[$wooKey] = $val;
        }
      }
    }

    // Bild (optional): erwartet ['image' => ['src' => 'https://...']]
    $img = $variation->getAttribute('image_url');
    if (!empty($img)) {
      $payload['image'] = ['src' => (string) $img];
    }

    // ---- Attributes (variation-determining) ---------------------------------
    // Woo erwartet pro Variation: 'attributes' => [ ['name' => 'Size', 'option' => 'M'], ... ]
    $attrs = [];
    foreach ($attrMap as $localCol => $wooName) {
      $val = $variation->getAttribute($localCol);
      if (!is_null($val) && $val !== '') {
        $attrs[] = [
          'name'   => (string) $wooName,
          'option' => (string) $val,
        ];
      }
    }
    if (!empty($attrs)) {
      $payload['attributes'] = $attrs;
    }

    // ---- Meta-Daten (EAN/GTIN/MPN) ------------------------------------------
    // Viele Shops/Plugins (z. B. EAN for Woo) lesen diese Keys direkt an der Variation.
    $meta = [];

    $mpnKey = $metaKeys['mpn']  ?? 'mpn';
    $eanKey = $metaKeys['ean']  ?? 'ean';
    $gtinKey = $metaKeys['gtin'] ?? 'gtin';

    $mpn  = $variation->getAttribute($colMpn);
    $ean  = $variation->getAttribute($colEan);
    $gtin = $variation->getAttribute($colGtin);

    if (!empty($mpn))  $meta[] = ['key' => (string) $mpnKey,  'value' => (string) $mpn];
    if (!empty($ean))  $meta[] = ['key' => (string) $eanKey,  'value' => (string) $ean];
    if (!empty($gtin)) $meta[] = ['key' => (string) $gtinKey, 'value' => (string) $gtin];

    if (!empty($meta)) {
      $payload['meta_data'] = $meta;
    }

    // ---- Fertig --------------------------------------------------------------
    return $payload;
  }
}
