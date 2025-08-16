<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repräsentiert ein einzelnes Produktbild.
 *
 * Jedes Bild ist einem Produkt zugeordnet und kann als Hauptbild (`is_main`)
 * markiert werden.
 */
class ProductImage extends Model
{
  /**
   * Die Attribute, die massenweise zugewiesen werden können.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'product_id',
    'url',
    'is_main',
  ];

  /**
   * Die Attribut-Casts für das Modell.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'is_main' => 'boolean',
  ];

  /**
   * Definiert die n:1-Beziehung zum übergeordneten Produkt.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function product(): BelongsTo
  {
    return $this->belongsTo(Product::class);
  }
}
