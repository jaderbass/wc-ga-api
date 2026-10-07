<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetzlTranslation extends Model
{
    protected $fillable = [
        'source_column',
        'source_text',
        'translated_text',
        'source_lang',
        'target_lang',
        'provider',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];
}
