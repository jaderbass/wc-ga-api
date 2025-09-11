<?php

namespace App\Support\Woo\ManufacturerMaps;

use App\Support\Woo\Transformers;

final class EdelridMap
{
  /**
   * Baut die WC-Felder für ein Produkt (ohne Varianten).
   * $product = dein Eloquent Model (products).
   */
  public static function product(array $product): array
  {
    // Beispiel: Felder, die direkt aus DB kommen oder leicht transformiert werden
    $payload = [
      'name'               => $product['product_name'] ?? '',
      'slug'               => $product['slug'] ?? Transformers::slug($product['product_name'] ?? ''),
      'sku'                => $product['product_number'] ?? null,
      'description'        => $product['description'] ?? '',
      'short_description'  => $product['short_description'] ?? '',
      'ean'                => $product['ean'] ?? null, // falls du ean als Meta exportieren willst, kannst du auch meta:_ean o.ä. nutzen
      'stock_status'       => $product['stock_status'] ?? 'instock',
      'manage_stock'       => (int)($product['manage_stock'] ?? false),
      'stock_quantity'     => (int)($product['stock_quantity'] ?? 0),
    ];

    // Maße/Gewicht, wenn Rohfeld vorhanden (z.B. "10 x 5 x 2 cm" aus Hersteller)
    if (!empty($product['dimensions_raw'])) {
      [$L, $W, $H] = Transformers::dimsToMm($product['dimensions_raw']);
      $payload['length'] = $L;
      $payload['width']  = $W;
      $payload['height'] = $H;
    } else {
      // oder aus bereits normalisierten mm-Feldern
      $payload['length'] = (int)($product['length_mm'] ?? 0);
      $payload['width']  = (int)($product['width_mm']  ?? 0);
      $payload['height'] = (int)($product['height_mm'] ?? 0);
    }

    if (!empty($product['weight_raw'])) {
      $payload['weight'] = Transformers::weightToGrams($product['weight_raw']);
    } else {
      $payload['weight'] = (int)($product['weight_g'] ?? 0);
    }

    return array_filter($payload, fn($v) => $v !== null && $v !== '');
  }

  /**
   * Für eine Variante – liest attributes_json und baut die *_i_-Spalten.
   */
  public static function variation(array $variation): array
  {
    $base = [
      'sku' => $variation['sku'] ?? null,
      'ean' => $variation['ean'] ?? null,
      'manage_stock'   => (int)($variation['manage_stock'] ?? false),
      'stock_quantity' => (int)($variation['stock_quantity'] ?? 0),
      'stock_status'   => $variation['stock_status'] ?? 'instock',
      'weight'         => (int)($variation['weight_g'] ?? 0),
      'length'         => (int)($variation['length_mm'] ?? 0),
      'width'          => (int)($variation['width_mm'] ?? 0),
      'height'         => (int)($variation['height_mm'] ?? 0),
    ];

    $attrs = [];
    if (!empty($variation['attributes_json']) && is_array($variation['attributes_json'])) {
      $attrs = $variation['attributes_json'];
    } elseif (!empty($variation['attributes_json']) && is_string($variation['attributes_json'])) {
      $decoded = json_decode($variation['attributes_json'], true);
      if (is_array($decoded)) $attrs = $decoded;
    }

    return array_filter(array_merge($base, Transformers::buildAttributeColumns($attrs)), fn($v) => $v !== null && $v !== '');
  }
}
