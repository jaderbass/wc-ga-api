<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetzlCategoryMapping extends Model
{
    protected $fillable = [
        'source_category',
        'source_subcategory',
        'translated_category',
        'translated_subcategory',
        'petzl_path',
        'is_reviewed',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_reviewed' => 'boolean',
        'is_active' => 'boolean',
    ];
}
