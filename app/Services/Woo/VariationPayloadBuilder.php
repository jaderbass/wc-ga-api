<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Log;

class VariationPayloadBuilder
{
  /**
   * Kompatible Signatur (falls dein Code 'build' aufruft).
   */
  public function build(Product $parent, ProductVariation $variation): array
  {
    return $this->buildSinglePayload($parent, $variation);
  }

  /**
   * Hauptmethode: baut das /products/{id}/variations Payload.
   * - Setzt Attribute (pa_size/pa_color, etc.)
   * - Fügt Preise/Lager/Dims/sku hinzu, sofern vorhanden
   */
  public function buildSinglePayload(Product $parent, ProductVariation $variation): array
  {
    $attrs = $this->buildAttributesFrom($variation, $parent);

    if (empty($attrs)) {
      Log::warning('VariationPayloadBuilder: no attributes for variation', [
        'variation_id' => $variation->id,
        'present' => [
          'id' => $variation->id,
          'product_id' => $variation->product_id,
          'sku' => $variation->sku,
          'stock_status' => $variation->stock_status,
          // 'regular_price_cents' => $variation->regular_price_cents,
          // 'sale_price_cents' => $variation->sale_price_cents,
          'weight_g' => $variation->weight_g,
          'length_mm' => $variation->length_mm,
          'width_mm' => $variation->width_mm,
          'height_mm' => $variation->height_mm,
          'manage_stock' => $variation->manage_stock,
          'backorders' => $variation->backorders,
        ],
      ]);
    }

    $payload = [
      'sku'        => $variation->sku ?: null,
      'attributes' => array_values($attrs), // Woo erwartet flaches Array
    ];

    // Preise (Cents → String in Woo)
    if ($variation->regular_price_cents > 0) {
      $payload['regular_price'] = number_format($variation->regular_price_cents / 100, 2, '.', '');
    }
    if ($variation->sale_price_cents > 0) {
      $payload['sale_price'] = number_format($variation->sale_price_cents / 100, 2, '.', '');
    }

    // Lager
    if ($variation->manage_stock) {
      $payload['manage_stock'] = true;
      if (property_exists($variation, 'stock_quantity') && $variation->stock_quantity !== null) {
        $payload['stock_quantity'] = (int) $variation->stock_quantity;
      }
    }
    if (!empty($variation->stock_status)) {
      $payload['stock_status'] = $variation->stock_status; // e.g. 'instock'/'outofstock'
    }
    if (!empty($variation->backorders)) {
      $payload['backorders'] = $variation->backorders; // 'no'|'notify'|'yes'
    }

    // Dimensionen/Gewicht (Woo erwartet Strings)
    $dims = [
      'weight' => $variation->weight_g ? (string) ($variation->weight_g / 1000) : null, // kg
      'length' => $variation->length_mm ? (string) ($variation->length_mm / 10) : null, // cm (falls so gewünscht)
      'width'  => $variation->width_mm  ? (string) ($variation->width_mm / 10) : null,
      'height' => $variation->height_mm ? (string) ($variation->height_mm / 10) : null,
    ];
    $dims = array_filter($dims, fn($v) => $v !== null && $v !== '');
    if (!empty($dims)) {
      $payload['dimensions'] = $dims;
    }

    Log::debug('VariationPayloadBuilder: single payload built', [
      'product_id'   => $parent->id,
      'variation_id' => $variation->id,
      'sku'          => $variation->sku,
      'attributes'   => $payload['attributes'],
    ]);

    return $payload;
  }

  /**
   * Baut die Woo-Attribute der Variante.
   * - nutzt Taxonomie-Attribute (pa_*) falls Wert vorhanden
   * - berücksichtigt mehrere mögliche Feldnamen (de/en)
   */
  private function buildAttributesFrom(ProductVariation $v, Product $parent): array
  {
    // Mapping: lokale Feldnamen → Woo Attribut-Slug
    // Passe die Keys links an deine echten Spalten an (z. B. 'farbe', 'groesse').
    $candidates = [
      // slug         // mögliche Feldnamen
      'pa_color' => ['color', 'farbe', 'colour'],
      'pa_size'  => ['size', 'groesse', 'größe', 'gr'],
      // weitere Beispiele:
      // 'pa_length' => ['length_label', 'laenge'],
      // 'pa_width'  => ['width_label', 'breite'],
    ];

    $attrs = [];

    foreach ($candidates as $wooAttrSlug => $fields) {
      $val = $this->firstNonEmpty($v, $fields);

      if ($val === null || $val === '') {
        continue;
      }

      // Woo erwartet bei Taxonomie-Attributen: name = slug (pa_*), option = Wert (String)
      $attrs[] = [
        'name'   => $wooAttrSlug,
        'option' => (string) $val,
      ];
    }

    // Fallback: Wenn weiterhin leer, versuche generische Felder zusammenzufassen
    if (empty($attrs)) {
      // Beispiel: Variation hat 'attribute_1_name'/'attribute_1_value' Felder
      foreach (['1', '2', '3'] as $idx) {
        $n = $this->getValue($v, ["attribute_{$idx}_name", "attr{$idx}_name"]);
        $o = $this->getValue($v, ["attribute_{$idx}_value", "attr{$idx}_value"]);
        if ($n && $o) {
          $attrs[] = [
            'name'   => $this->normalizeAttrName($n), // 'pa_*' wenn passt, sonst Rohname
            'option' => (string) $o,
          ];
        }
      }
    }

    return $attrs;
  }

  private function firstNonEmpty(ProductVariation $v, array $fieldNames): ?string
  {
    foreach ($fieldNames as $f) {
      if (isset($v->{$f}) && $v->{$f} !== null && $v->{$f} !== '') {
        return (string) $v->{$f};
      }
    }
    return null;
  }

  private function getValue(ProductVariation $v, array $fieldNames): ?string
  {
    foreach ($fieldNames as $f) {
      if (property_exists($v, $f) && $v->{$f} !== null && $v->{$f} !== '') {
        return (string) $v->{$f};
      }
    }
    return null;
  }

  private function normalizeAttrName(string $name): string
  {
    $n = trim(mb_strtolower($name));
    // Mappe offensichtliche Namen auf pa_* Slugs
    return match ($n) {
      'color', 'farbe', 'colour' => 'pa_color',
      'size', 'größe', 'groesse', 'gr' => 'pa_size',
      default => $n, // Rohname, Woo akzeptiert auch Nicht-Taxonomie-Attribute
    };
  }
}
