<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = ['product_id', 'user_id','author_name','author_email', 'image_path', 'rating', 'comment', 'status'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
