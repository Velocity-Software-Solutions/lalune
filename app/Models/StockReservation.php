<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = [
        'product_id',
        'product_stock_id',
        'quantity',
        'session_key',
        'user_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'status' => 'bool',
        'expires_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function productStock()
    {
        return $this->belongsTo(ProductStock::class);
    }
    public function scopeActive($q)
    {
        return $q->where('status', 'active')
            ->where('expires_at', '>', now());
    }
}
