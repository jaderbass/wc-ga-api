<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lesende Momentaufnahme aller Produkte und Varianten aus dem WooCommerce-Shop.
 */
class WooSnapshot extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'shop_id',
        'status',
        'products_count',
        'variations_count',
        'error',
        'started_at',
        'finished_at',
        'matched_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'matched_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WooSnapshotItem::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public static function latestCompleted(): ?self
    {
        return self::query()
            ->where('status', self::STATUS_COMPLETED)
            ->latest('id')
            ->first();
    }
}
