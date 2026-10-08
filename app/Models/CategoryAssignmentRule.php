<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Hersteller-Zuordnung: ordnet eine Herstellerkategorie oder ein Stichwort
 * im Produktnamen einer oder mehreren Shop-Kategorien zu.
 *
 * @see \App\Services\Categories\ManufacturerCategoryResolver
 */
class CategoryAssignmentRule extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'source_category',
        'keyword',
        'sort_order',
        'exclude',
        'is_reviewed',
        'notes',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'exclude' => 'boolean',
        'is_reviewed' => 'boolean',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Stichwort-Regel (true) oder Zuordnung einer Herstellerkategorie (false).
     */
    public function isKeywordRule(): bool
    {
        return filled($this->keyword);
    }

    /**
     * Stellt sicher, dass für eine Herstellerkategorie ein Zuordnungs-Eintrag
     * existiert. Neue Einträge sind leer und tauchen als "offen" im Backend auf.
     */
    public static function ensureForSource(?int $manufacturerId, ?string $sourceCategory): ?self
    {
        $sourceCategory = trim((string) $sourceCategory);

        if ($manufacturerId === null || $sourceCategory === '') {
            return null;
        }

        return static::query()->firstOrCreate([
            'manufacturer_id' => $manufacturerId,
            'source_category' => $sourceCategory,
            'keyword' => null,
        ]);
    }
}
