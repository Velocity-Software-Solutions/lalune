<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'sku',
        'currency',
        'stripe_price_id',
        'stripe_product_id',
        'quantity',
        'unit_price_cents',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Helpers
    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_cents / 100;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }
}
