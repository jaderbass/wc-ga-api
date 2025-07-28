<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
  protected $fillable = [
    'attribute_id',
    'value',
    'slug',
  ];

  public function attribute(): BelongsTo
  {
    return $this->belongsTo(ProductAttribute::class, 'attribute_id');
  }
}
