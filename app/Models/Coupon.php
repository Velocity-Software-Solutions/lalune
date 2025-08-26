<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Coupon extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'value',
        'min_order_amount',
        'usage_limit',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];
   public function orders()
{
    return $this->hasMany(Order::class);
}


}
