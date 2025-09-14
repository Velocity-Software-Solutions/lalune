<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        "product_id",
        "color_id",
        "size_id",
        "quantity_on_hand"
    ];

    public function reservations()
    {
        return $this->hasMany(StockReservation::class, 'product_stock_id');
    }
}
