<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'manufacturer',
        'manufacturercountry',
        'website',
        'api_url',
        'api_user',
        'api_password',
        'api_token',
        'import_type',
        'notes',
    ];


    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
