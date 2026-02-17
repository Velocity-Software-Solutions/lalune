<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
        protected $fillable = [
        'product_id',
        'color_id',
        'size_id',
        'price',
        'discounted_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function color()
    {
        return $this->belongsTo(ProductColor::class, 'color_id');
    }

    public function size()
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }
}
