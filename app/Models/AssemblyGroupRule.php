<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyGroupRule extends Model
{
    protected $fillable = [
        'name',
        'field',
        'operator',
        'value',
        'assembly_group',
        'sort_order',
        'is_active',
    ];

    public function matches(Product $product): bool
    {
        if ($this->field === 'product_name') {
            $haystack = mb_strtolower($product->product_name ?? '');
            $needle = mb_strtolower($this->value);

            return match ($this->operator) {
                'contains' => str_contains($haystack, $needle),
                default => false,
            };
        }

        return false;
    }
}
