<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    

    protected $fillable = [
        'user_id',
        'coupon_id',
        'shipping_option_id',

        'order_number',
        'stripe_session_id',
        'stripe_payment_intent',
        'stripe_customer_id',

        'full_name',
        'email',
        'phone',

        'currency',
        'subtotal_cents',
        'discount_cents',
        'shipping_cents',
        'tax_cents',
        'total_cents',

        'payment_status',
        'order_status',
        'payment_method',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',

        'shipping_address_json',
        'billing_address_json',

        'coupon_code',
        'ip_address',
        'user_agent',
        'snapshot',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'shipping_address_json' => 'array',
        'billing_address_json'  => 'array',
        'snapshot'              => 'array',
        'metadata'              => 'array',
        'paid_at'               => 'datetime',
        'shipped_at'            => 'datetime',
        'delivered_at'          => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingOption()
    {
        return $this->belongsTo(ShippingOption::class);
    }

    // Helpers
    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }

    public function getSubtotalAttribute(): float
    {
        return $this->subtotal_cents / 100;
    }

        // Scope for "paid" orders
public function scopePaid($query)
{
    return $query->where('payment_status', 'paid');
}

}
