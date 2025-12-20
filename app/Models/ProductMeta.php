<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ProductMeta
 *
 * Speichert zusätzliche Meta-Informationen für Produkte und Varianten.
 *
 * Verwendung:
 * - scope=product: Meta für das Produkt (variation_id = null)
 * - scope=variation: Meta für eine Variante (variation_id != null)
 *
 * Tabelle: product_meta
 */
class ProductMeta extends Model
{
  protected $table = 'product_meta';

  protected $fillable = [
    'product_id',
    'variation_id',
    'scope',
    'key',
    'value',
  ];

  public function product(): BelongsTo
  {
    return $this->belongsTo(Product::class);
  }

  public function variation(): BelongsTo
  {
    return $this->belongsTo(ProductVariation::class, 'variation_id');
  }
}
