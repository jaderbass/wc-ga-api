<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends Model
{
  protected $fillable = [
    'name',
    'slug',
  ];

  public function values(): HasMany
  {
    return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
  }
}
