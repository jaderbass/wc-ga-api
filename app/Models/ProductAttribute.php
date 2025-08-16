<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Repräsentiert ein Produktattribut (z.B. Farbe, Größe).
 */
class ProductAttribute extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'slug',
    'woo_attribute_id',
  ];

  /**
   * Definiert die 1:n-Beziehung zu den Attributwerten.
   */
  public function values(): HasMany
  {
    return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
  }
}
