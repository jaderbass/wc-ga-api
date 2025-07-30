<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerAudit extends Model
{
  protected $fillable = [
    'manufacturer_id',
    'field',
    'old_value',
    'new_value',
    'changed_by',
  ];

  public function manufacturer(): BelongsTo
  {
    return $this->belongsTo(Manufacturer::class);
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class, 'changed_by');
  }
}
