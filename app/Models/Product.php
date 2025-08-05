<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'woo_product_id',
        'sku',
        'name',
        'description',
        'short_description',
        'regular_price',
        'sale_price',
        'stock_quantity',
        'stock_status',
        'product_type',
        'manufacturer_id',
        'status',
        'slug',
        'woo_synced_at',

        // Zusätzliche Importfelder
        'productnumber',
        'eancode',
        'skucode',
        'productname',
        'price',
        'regularprice',
        'saleprice',
        'width',
        'length',
        'height',
        'unit',
        'unitprice',
        'pcsperbox',
        'boxwidth',
        'boxlength',
        'boxheight',
        'mpn',
        'weight',
    ];

    protected $casts = [
        'woo_synced_at' => 'datetime',
    ];

    /** Beziehungen */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
