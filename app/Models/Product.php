<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\Collection;


class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'price',
        'discount_price',
        'stock_quantity',
        'status',
        'category_id',
        'collection_id'

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

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class);
    }
    public function colors()
    {
        return $this->hasMany(ProductColor::class);
    }

    public function stock()
    {
        return $this->hasMany(ProductStock::class, 'product_id');
    }

    public function reservations()
    {
        return $this->hasMany(StockReservation::class)
            ->whereNull('product_stock_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews()
    {
        return $this->reviews()->where('status', 'approved')->latest();
    }

    public function getAverageRatingAttribute(): float
    {
        return round((float) $this->approvedReviews()->avg('rating') ?? 0, 1);
    }

    public function getReviewsCountAttribute(): int
    {
        return (int) $this->approvedReviews()->count();
    }

}
