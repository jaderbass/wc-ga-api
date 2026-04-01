<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regel zur automatischen Kategorisierung.
 */
class CategoryRule extends Model
{
    /**
     * Mass assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'keyword',
        'sort_order',
    ];

    /**
     * Zugehörige Kategorie.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
