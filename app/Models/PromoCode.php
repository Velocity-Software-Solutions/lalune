<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
        protected $table = 'promo_codes';

    protected $fillable = [
        'code',
        'discount_type',
        'value',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'times_used',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'expires_at'  => 'datetime',
        'value'       => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];

    /** Check if promo code is expired */
    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }

    /** Check if promo code has usage left */
    public function hasUsagesLeft(): bool
    {
        return is_null($this->usage_limit) || $this->times_used < $this->usage_limit;
    }

    /** Check if promo code is valid */
    public function isValid(float $orderSubtotal): bool
    {
        if (!$this->is_active) return false;
        if ($this->isExpired()) return false;
        if (!$this->hasUsagesLeft()) return false;
        if ($this->min_order_amount && $orderSubtotal < $this->min_order_amount) return false;

        return true;
    }

    /** Apply discount */
    public function calculateDiscount(float $orderSubtotal, float $shippingCost = 0): float
    {
        if (!$this->isValid($orderSubtotal)) {
            return 0;
        }

        return match ($this->discount_type) {
            'shipping'   => $shippingCost, // full shipping amount
            'fixed'      => min($this->value ?? 0, $orderSubtotal),
            'percentage' => round(($this->value ?? 0) / 100 * $orderSubtotal, 2),
            default      => 0,
        };
    }
}
