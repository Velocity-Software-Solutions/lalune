<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'price',         // unit price at time of order
        'quantity',
        'subtotal',      // price * quantity at time of order
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /* --------------------------------------------------------------
     | Relationships
     | -------------------------------------------------------------- */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Optional: keep historical reference to product;
     * product may be deleted later.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}