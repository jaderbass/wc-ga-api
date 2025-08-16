<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Repräsentiert einen spezifischen Wert eines Produktattributs (z.B. "Blau", "XL").
 */
class ProductAttributeValue extends Model
{
  use HasFactory;

  protected $fillable = [
    'attribute_id',
    'value',
    'slug',
    'woo_term_id',
  ];

  /**
   * Definiert die n:1-Beziehung zum übergeordneten Attribut.
   */
  public function attribute(): BelongsTo
  {
    return $this->belongsTo(ProductAttribute::class, 'attribute_id');
  }

  /**
   * Definiert die n:m-Beziehung zu den Produktvarianten.
   */
  public function variations(): BelongsToMany
  {
    return $this->belongsToMany(ProductVariation::class, 'product_variation_attribute_value');
  }
}
