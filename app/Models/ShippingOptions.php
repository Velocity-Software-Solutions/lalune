<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingOptions extends Model
{
    //
    protected $fillable = [
        'name',
        'name_ar',
        'price',
        'delivery_time',
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