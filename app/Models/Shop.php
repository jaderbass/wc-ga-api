<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Shop
 *
 * @property string $name
 * @property string $base_url
 * @property string $api_version
 * @property string $consumer_key
 * @property string $consumer_secret
 * @property string|null $webhook_secret
 * @property bool $is_default
 * @property array|null $rate_limit_json
 */
class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'api_version',
        'consumer_key',
        'consumer_secret',
        'webhook_secret',
        'is_default',
        'rate_limit_json',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'rate_limit_json' => 'array',
        'consumer_key' => 'encrypted:string',
        'consumer_secret' => 'encrypted:string',
        'webhook_secret' => 'encrypted:string',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $shop) {
            if (empty($shop->api_version)) {
                $shop->api_version = config('woo.default_api_version', 'wc/v3');
            }
        });

        static::saved(function () {
            // ensure exactly one default per environment
            $ids = static::where('is_default', true)->pluck('id')->toArray();
            if (count($ids) > 1) {
                $keepId = end($ids);
                static::where('is_default', true)->where('id', '!=', $keepId)->update(['is_default' => false]);
            }
        });
    }
}
