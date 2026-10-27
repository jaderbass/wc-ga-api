<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Geplanter Rabattwechsel eines Herstellers zu einem Stichtag.
 */
class ManufacturerDiscountChange extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'purchase_discount_1',
        'purchase_discount_2',
        'valid_from',
        'applied_at',
        'note',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'applied_at' => 'datetime',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Fällig: Stichtag erreicht, noch nicht übernommen.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNull('applied_at')->whereDate('valid_from', '<=', today());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('applied_at');
    }
}
