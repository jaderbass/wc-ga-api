<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'productnumber',
        'eancode',
        'skucode',
        'productname',
        'description',
        'shortdescription',
        'price',
        'regularprice',
        'saleprice',
        'width',
        'length',
        'height',
        'hasoptions',
        'manufacturer_id',
        'unit',
        'unitprice',
        'pcsperbox',
        'boxwidth',
        'boxlength',
        'boxheight',
        'mpn',
        'weight'
    ];

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
