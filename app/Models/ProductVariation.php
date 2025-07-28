<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
    'attributes',
  ];

  protected $casts = [
    'attributes' => 'array', // JSON: {"size": "M", "color": "Blue"}
  ];

  public function product(): BelongsTo
  {
    return $this->belongsTo(Product::class);
  }
}
