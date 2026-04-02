<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Protokolliert einen Lauf zur Neuzuordnung von Produktkategorien.
 */
class CategoryResyncRun extends Model
{
    /**
     * Mass assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'processed',
        'total',
        'started_at',
        'finished_at',
        'message',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
