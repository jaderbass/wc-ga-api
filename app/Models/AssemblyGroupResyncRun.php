<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyGroupResyncRun extends Model
{
    protected $fillable = [
        'status',
        'processed',
        'updated',
        'total',
    ];
}
