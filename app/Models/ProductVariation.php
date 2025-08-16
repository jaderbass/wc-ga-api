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
    'regular_price',
    'sale_price',
    'stock_quantity',
    'stock_status',
  ];

  protected $casts = [
    // Die 'attributes' Spalte wird nicht mehr als JSON gecastet, da sie entfernt wird.
  ];

  public function product(): BelongsTo
  {
    return $this->belongsTo(Product::class);
  }

  /**
   * The attribute values that belong to the ProductVariation.
   */
  public function attributeValues(): BelongsToMany
  {
    return $this->belongsToMany(ProductAttributeValue::class, 'product_variation_attribute_value');
  }
}
