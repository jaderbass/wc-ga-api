<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ManufacturerAudit;

/**
 * Modell für Hersteller.
 *
 * Repräsentiert einen Hersteller, dessen Produkte und API-Zugangsdaten.
 */
class Manufacturer extends Model
{
    use HasFactory;

    /**
     * Die Attribute, die massenweise befüllt werden können.
     *
     * @var array
     */
    protected $fillable = [
        'manufacturer',
        'manufacturercountry',
        'website',
        'api_url',
        'api_user',
        'api_password',
        'api_token',
        'import_type',
        'notes',
    ];

    /**
     * Beziehung: Ein Hersteller hat viele Produkte.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Beziehung: Ein Hersteller hat viele Audit-Einträge.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function audits()
    {
        return $this->hasMany(ManufacturerAudit::class);
    }

    /**
     * Scope a query to only include active manufacturers.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }


    /**
     * Die Attribut-Casts.
     *
     * @var array
     */
    protected $casts = [
        'api_password_changed_at' => 'datetime',
    ];
}
