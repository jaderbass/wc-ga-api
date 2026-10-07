<?php

namespace App\Models;

use App\Services\ColorTranslator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ColorTranslation extends Model
{
    public const COLOR_ATTRIBUTE_SLUGS = ['farbe', 'color'];

    protected $fillable = [
        'source_value',
        'source_slug',
        'translated_value',
        'is_auto',
        'is_reviewed',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_auto' => 'boolean',
        'is_reviewed' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @var array<string,array{value:?string,expires:int}> */
    private static array $displayCache = [];

    public static function ensureFor(string $rawValue): ?self
    {
        $rawValue = trim($rawValue);
        $slug = Str::slug($rawValue);

        if ($rawValue === '' || $slug === '') {
            return null;
        }

        $auto = ColorTranslator::auto($rawValue);

        return self::firstOrCreate(
            ['source_slug' => $slug],
            [
                'source_value' => $rawValue,
                'translated_value' => $auto,
                'is_auto' => $auto !== null,
                'is_reviewed' => $auto !== null,
                'is_active' => true,
            ]
        );
    }

    /** Liefert die deutsche Anzeige einer Farbe, sonst den Originalwert. */
    public static function display(string $rawValue): string
    {
        $rawValue = trim($rawValue);
        $slug = Str::slug($rawValue);

        if ($rawValue === '' || $slug === '') {
            return $rawValue;
        }

        $cached = self::$displayCache[$slug] ?? null;

        if ($cached === null || $cached['expires'] < time()) {
            try {
                $row = self::query()
                    ->where('source_slug', $slug)
                    ->where('is_active', true)
                    ->first();
            } catch (\Throwable) {
                // Tabelle fehlt (z. B. vor der Migration): Originalwert anzeigen.
                $row = null;
            }

            $translated = $row?->translated_value;
            $cached = self::$displayCache[$slug] = [
                'value' => filled($translated) ? $translated : null,
                'expires' => time() + 60,
            ];
        }

        return $cached['value'] ?? $rawValue;
    }

    public static function flushDisplayCache(): void
    {
        self::$displayCache = [];
    }

    /** Übersetzt den Wert nur, wenn der Attributname/-slug eine Farbe bezeichnet. */
    public static function displayFor(string $attributeNameOrSlug, string $value): string
    {
        if (! preg_match('/farbe|colou?r/iu', $attributeNameOrSlug)) {
            return $value;
        }

        return self::display($value);
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty('translated_value') && ! $model->isDirty('is_auto')) {
                $model->is_auto = false;
            }
        });
        static::saved(fn () => self::$displayCache = []);
        static::deleted(fn () => self::$displayCache = []);
    }
}
