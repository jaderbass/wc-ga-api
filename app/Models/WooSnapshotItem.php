<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Produkt oder eine Variante aus der Shop-Momentaufnahme inklusive Abgleich-Ergebnis.
 */
class WooSnapshotItem extends Model
{
    public const TYPE_VARIATION = 'variation';

    public const TYPE_VARIABLE = 'variable';

    public const MATCH_SKU = 'sku';

    public const MATCH_EAN = 'ean';

    public const MATCH_VIA_VARIATIONS = 'variations';

    public const MATCH_AMBIGUOUS = 'ambiguous';

    public const MATCH_SHOP_ONLY = 'shop_only';

    public const MATCH_NO_KEY = 'no_key';

    public const MATCH_LABELS = [
        self::MATCH_SKU => 'Gefunden (SKU)',
        self::MATCH_EAN => 'Gefunden (nur EAN)',
        self::MATCH_VIA_VARIATIONS => 'Gefunden (über Varianten)',
        self::MATCH_AMBIGUOUS => 'Mehrdeutig',
        self::MATCH_SHOP_ONLY => 'Nur im Shop',
        self::MATCH_NO_KEY => 'Ohne SKU/EAN',
    ];

    public const TYPE_LABELS = [
        'simple' => 'Einfach',
        self::TYPE_VARIABLE => 'Variabel',
        self::TYPE_VARIATION => 'Variante',
        'grouped' => 'Gruppiert',
        'external' => 'Extern',
    ];

    protected $fillable = [
        'woo_snapshot_id',
        'woo_id',
        'woo_parent_id',
        'type',
        'status',
        'sku',
        'sku_key',
        'ean',
        'ean_key',
        'name',
        'regular_price',
        'payload',
        'match_status',
        'match_method',
        'matched_product_id',
        'matched_variation_id',
        'candidate_count',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(WooSnapshot::class, 'woo_snapshot_id');
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function matchedVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'matched_variation_id');
    }
}
