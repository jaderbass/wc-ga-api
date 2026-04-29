<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
  protected $fillable = [
    'product_id',
    'woo_variation_id',
    'sku',
    'manufacturer_price_cents',
    'regular_price',
    'sale_price',
    'stock_quantity',
    'stock_status',
    // Maße & Gewicht aus Petzl (und anderen Herstellern)
    'weight',     // in Gramm
    'length_mm',  // in Millimetern
    'width_mm',
    'height_mm',
    // Attribute und EAN aus Aliens
    'attributes_json',
    'ean',
    'external_id',
    'slug',
  ];

  protected $casts = [
    'attributes_json'           => 'array',
    'manage_stock'              => 'bool',
    'weight'                    => 'integer',
    'length_mm'                 => 'integer',
    'width_mm'                  => 'integer',
    'height_mm'                 => 'integer',
    'manufacturer_price_cents'  => 'integer',
  ];

  // Anzeige-Name für Filament
  public function getDisplayNameAttribute(): string
  {
    $sku = (string) ($this->sku ?? '');

    $attrs = $this->attributes_json ?? [];

    if (!is_array($attrs)) {
      $attrs = [];
    }

    // Aliens relevante Attribute Groups (kannst du jederzeit erweitern)
    $keys = [
      'Attribute Group: Farbe',
      'Attribute Group: Karabinerfarbe',
      'Attribute Group: Schlingenlänge | Farbe',
      'Attribute Group: Karabinerverschluß',
      'Attribute Group: Karabinerversion',
      'Attribute Group: Schlingenmaterial',
      'Attribute Group: Größe',
      'Attribute Group: Länge',
    ];

    $parts = [];

    foreach ($keys as $k) {
      $v = $attrs[$k] ?? null;

      if (is_string($v)) {
        $v = trim($v);
        if ($v !== '') {
          $parts[] = $v;
        }
      }
    }

    $parts = array_values(array_unique($parts));

    return $parts ? ($sku . ' – ' . implode(' / ', $parts)) : $sku;
  }


  /*  protected $casts = [
    // Die 'attributes' Spalte wird nicht mehr als JSON gecastet, da sie entfernt wird.
  ]; */

  public function product(): BelongsTo
  {
    return $this->belongsTo(Product::class);
  }

  /**
   * The attribute values that belong to the ProductVariation.
   */
  public function attributeValues(): BelongsToMany
  {
    return $this->belongsToMany(
      ProductAttributeValue::class,
      'product_variation_attribute_value',
      'product_variation_id',
      'product_attribute_value_id'
    );
  }
}
