<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingOptionCity extends Model
{
    protected $fillable = [
        "shipping_option_id",
        "city"
    ];
}
