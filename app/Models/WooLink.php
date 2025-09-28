<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * WooLink Model
 * Verknüpft lokale SKU mit Woo-SKU und IDs.
 */
class WooLink extends Model
{
    protected $table = 'woo_links';

    protected $fillable = [
        'shop_id',
        'local_sku',
        'woo_sku',
        'woo_product_id',
        'woo_variation_id',
        'ean',
        'mpn',
        'protect_sku',
        'confidence',
    ];
}
