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

    /** @var array<string,?string> */
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

        if (! array_key_exists($slug, self::$displayCache)) {
            $row = self::query()
                ->where('source_slug', $slug)
                ->where('is_active', true)
                ->first();

            $translated = $row?->translated_value;
            self::$displayCache[$slug] = filled($translated) ? $translated : null;
        }

        return self::$displayCache[$slug] ?? $rawValue;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$displayCache = []);
        static::deleted(fn () => self::$displayCache = []);
    }
}
