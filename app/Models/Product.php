<?php

/**
 * @file
 * Product Model – ergänzt um die Alias-Beziehung `variants()`.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'author_id',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'image_urls' => 'array',
        'dimension_length_mm' => 'integer',
        'dimension_width_mm'  => 'integer',
        'dimension_height_mm' => 'integer',
        'weight'              => 'integer',
        'box_length'          => 'integer',
        'box_width'           => 'integer',
        'box_height'          => 'integer',
    ];

    /**
     * Benutzer, der den Import ausgelöst hat.
     */
    public function author()
    {
        return $this->belongsTo(\App\Models\User::class, 'author_id');
    }

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

    /**
     * Liefert eine bereinigte Liste von Bild-URLs zur Anzeige.
     *
     * Quelle ist das Feld {@see Product::$image_urls}, das als Array,
     * JSON-String oder kommagetrennter String gespeichert sein kann.
     *
     * Es werden ausschließlich absolute HTTP/HTTPS-URLs zurückgegeben.
     * Reine Dateinamen oder relative Pfade werden ignoriert – es findet
     * keine automatische Ergänzung einer Basis-URL mehr statt.
     *
     * @return array<int, string> Liste gültiger Bild-URLs
     */
    public function getDisplayImageUrlsAttribute(): array
    {
        // Rohdaten: kann Array, JSON-String oder kommagetrennter String sein
        $raw = $this->image_urls ?? null; // Feldnamen ggf. anpassen

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            // Versuchen, JSON zu dekodieren
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $values = $decoded;
            } else {
                // Fallback: Komma- oder Semikolon-getrennter String
                $values = array_map('trim', preg_split('/[;,]+/', $raw));
            }
        } elseif (is_array($raw)) {
            $values = $raw;
        } else {
            return [];
        }

        $urls = [];

        foreach ($values as $val) {
            if (! is_string($val)) {
                continue;
            }

            $val = trim($val);
            if ($val === '') {
                continue;
            }

            // Nur echte http/https-URLs zulassen
            if (
                str_starts_with($val, 'http://')
                || str_starts_with($val, 'https://')
            ) {
                $urls[] = $val;
            }
            // Reine Dateinamen oder relative Pfade werden bewusst ignoriert
        }

        return $urls;
    }

    public function meta(): HasMany
    {
        return $this->hasMany(\App\Models\ProductMeta::class);
    }

    /**
     * Key->Value-Liste für UI-Ausgabe (z. B. Normen, Bruchlast, Material, Farbe …).
     * Gruppiert gleiche Attribute und fasst mehrere Werte zusammen.
     *
     * @return array<int, array{key:string, value:string}>
     */
    public function technicalAttributesKv(): array
    {
        $out = [];

        // 1) Direkte Produktfelder (schnell + stabil)
        $direct = [
            'Typ' => $this->type,
            'Material' => $this->materials,
            'Normen' => $this->norms,
            'Zertifizierung' => $this->certification,
            'Herkunft' => $this->made_in,
            'Größe' => $this->size,
        ];

        $labelMap = [
            'feature.normen' => 'Normen',
            'feature.material' => 'Material',
            'feature.typ' => 'Typ',
            'feature.farbe' => 'Farbe',

            'feature.festigkeit_bruchlast_belastbarkeit_kn' => 'Bruchlast / Festigkeit [kN]',
            'feature.mindestbruchlast_kn' => 'Mindestbruchlast [kN]',
            'feature.mindestbruchlast_geschlossen_kn' => 'Mindestbruchlast geschlossen [kN]',
            'feature.mindestbruchlast_offen_kn' => 'Mindestbruchlast offen [kN]',
            'feature.mindestbruchlast_quer_kn' => 'Mindestbruchlast quer [kN]',
            'feature.mindestbruchlast_laengs_kn' => 'Mindestbruchlast längs [kN]',

            'feature.max_fangstoss_kn' => 'Max. Fangstoß [kN]',
            'feature.anzahl_normstuerze_uiaa' => 'Anzahl Normstürze [UIAA]',
            'feature.statische_dehnung' => 'Statische Dehnung',
            'feature.dynamische_dehnung' => 'Dynamische Dehnung',
            'feature.durchmesser_mm' => 'Durchmesser [mm]',
        ];

        $metaRows = $this->meta()
            ->where('scope', 'product')
            ->whereNull('variation_id')
            ->whereIn('key', array_keys($labelMap))
            ->get(['key', 'value']);

        foreach ($metaRows as $row) {
            $k = (string) $row->key;
            $v = $row->value;

            if ($v === null || $v === '') {
                continue;
            }

            if (is_string($v)) {
                $v = trim($v);
            }

            $out[] = [
                'key' => $labelMap[$k] ?? $k,
                'value' => is_scalar($v) ? (string) $v : json_encode($v),
            ];
        }

        foreach ($direct as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                continue;
            }

            $out[] = [
                'key' => $key,
                'value' => (string) $value,
            ];
        }

        // 2) Meta-Felder (hier landen idealerweise Bruchlast, EN-Normen, UIAA, etc.)
        // Nur product-scope, ohne variation_id
        $metaRows = $this->meta()
            ->where('scope', 'product')
            ->whereNull('variation_id')
            ->get(['key', 'value']);

        // Optional: nur relevante Meta-Keys zeigen (Whitelist)
        // Wenn du erstmal alles sehen willst: $allowedKeys = null;
        $allowedKeys = null;

        foreach ($metaRows as $row) {
            $k = (string) $row->key;
            $v = $row->value;

            if ($allowedKeys !== null && ! in_array($k, $allowedKeys, true)) {
                continue;
            }

            if ($v === null || $v === '') {
                continue;
            }

            // JSON in Meta hübsch machen (falls Values als JSON gespeichert werden)
            if (is_string($v)) {
                $trim = trim($v);
                $decoded = json_decode($trim, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $v = implode(', ', array_map(
                            fn($x) => is_scalar($x) ? (string) $x : json_encode($x),
                            $decoded
                        ));
                    } elseif (is_scalar($decoded)) {
                        $v = (string) $decoded;
                    }
                }
            }

            $out[] = [
                'key' => $k,
                'value' => is_scalar($v) ? (string) $v : json_encode($v),
            ];
        }

        // Optional: nach key sortieren
        usort($out, fn($a, $b) => strcasecmp($a['key'], $b['key']));

        return $out;
    }

}
