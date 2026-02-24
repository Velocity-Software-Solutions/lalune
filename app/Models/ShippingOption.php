<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingOption extends Model
{
    //
    protected $fillable = [
        'name',
        'price',
        'delivery_time',
        'tax_percentage',
        'description',
        'country',
        'region'
    ];
    // in ShippingOption.php
    public function cities()
    {
        return $this->hasMany(ShippingOptionCity::class,'shipping_option_id');
    }
}