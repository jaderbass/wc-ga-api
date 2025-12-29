<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImportRun extends Model
{
  use HasUuids;

  public $incrementing = false;
  protected $keyType = 'string';

  protected $fillable = [
    'id',
    'manufacturer_id',
    'source_type',
    'source',
    'author_id',
    'status',
    'error_message',
    'started_at',
    'finished_at',
  ];

  protected $casts = [
    'started_at'  => 'datetime',
    'finished_at' => 'datetime',
  ];
}
