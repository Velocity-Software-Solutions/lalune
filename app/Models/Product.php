<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;
use App\Models\Category;

class Product extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'sku',
        'description',
        'description_ar',
        'price',
        'discount_price',
        'stock_quantity',
        'condition',
        'status',
        'category_id'
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('thumbnail');
    }

    public function thumbnail()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('thumbnail')->first();
    }


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

}
