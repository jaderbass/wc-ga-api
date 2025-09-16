<?php

/**
 * @file
 * Product Model – ergänzt um die Alias-Beziehung `variants()`.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;


/**
 * Class Product
 *
 * Produktmodell.
 * Generiert bei create/update automatisch einen eindeutigen Slug,
 * falls keiner gesetzt ist (Basis: product_name → sku → uuid).
 * 
 * Aktualisiert gemäß Änderungsanforderung (2025-08-25):
 *  - Neue Felder: external_url, declaration_of_compliance, manual_url, size,
 *    certification, author_firstname, author_lastname, author_name, author_mail
 *  - Entfernte Felder: regular_price, sale_price, stock_quantity, status,
 *    woo_synced_at, price, unit_price, pcs_per_box, mpn
 *
 * Hinweis: Maßeinheits- und Maß-Felder (width, length, height, unit,
 * box_width, box_length, box_height, weight) werden als STRING beibehalten,
 * da Lieferanten Zahl+Einheit kombiniert liefern können.
 */
class Product extends Model
{
    protected $fillable = [
        'woo_product_id',
        'sku',
        'description',
        'short_description',
        'stock_status',
        'product_type',
        'manufacturer_id',
        'slug',
        'product_number',
        'ean',
        'product_name',
        'width',
        'length',
        'height',
        'unit',
        'box_width',
        'box_length',
        'box_height',
        'weight',
        'external_url',
        'declaration_of_compliance',
        'manual_url',
        'size',
        'certification',
        'author_firstname',
        'author_lastname',
        'author_name',
        'author_mail',
    ];

    protected $guarded = ['id'];

    protected $casts = [];

    /**
     * Liefert die Produktvarianten (Alias für `variations()`).
     *
     * Dieser Alias wird vom Filament-RelationManager `ProductVariantRelationManager`
     * erwartet, da dort `protected static string $relationship = 'variants';`
     * gesetzt ist. So vermeiden wir einen Methoden-Namenskonflikt und können
     * weiterhin eine ggf. bereits existierende Methode `variations()` parallel nutzen.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductVariation>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariation::class, 'product_id');
    }

    // Optional (falls noch nicht vorhanden und du sie nutzt):
    /**
     * Liefert die Produktvarianten.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductVariation>
     */
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

    /**
     * Hookt sich in creating/updating ein, um Slug zu setzen.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (Product $p) {
            if (blank($p->slug)) {
                $p->slug = static::makeUniqueSlug($p);
            }
        });

        // falls jemand den slug im UI leert, beim Update neu setzen
        static::updating(function (Product $p) {
            if (blank($p->slug)) {
                $p->slug = static::makeUniqueSlug($p);
            }
        });

        static::saving(function ($p) {
            if ($p->isDirty('sku') && $p->sku === '') {
                $p->sku = null; // gegen '' in DB (UNIQUE-Index & MySQL-NULL-Handling)
            }
            if ($p->short_description === '') {
                $p->short_description = null;
            }
        });
    }

    /**
     * Erzeugt einen eindeutigen Slug aus Name/SKU; hängt bei Kollisionen -2, -3, … an.
     *
     * @param Product $p
     * @return string
     */
    protected static function makeUniqueSlug(Product $p): string
    {
        // Basis: Produktname, sonst SKU, sonst UUID
        $base = Str::slug($p->product_name ?: $p->sku ?: Str::uuid());
        $base = Str::limit($base, 190, ''); // Puffer, falls wir Suffixe anhängen

        $slug = $base;
        $i = 2;

        // Kollisionen vermeiden (bei update eigenen Datensatz ausschließen)
        while (static::query()
            ->when($p->exists, fn($q) => $q->whereKeyNot($p->getKey()))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug ?: Str::uuid()->toString();
    }
}
