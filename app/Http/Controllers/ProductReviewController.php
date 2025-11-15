<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product)
{
    $data = $request->validate([
        'author_name'  => 'required|string|max:100',
        'author_email' => 'nullable|email|max:150',
        'rating'       => 'required|numeric|min:0.5|max:5',
        'comment'      => 'nullable|string|max:2000',
        'image'        => 'nullable|image|max:4096', // 4MB
    ], [
        'author_name.required' => 'Please enter your name.',
        'rating.required'      => 'Please choose a rating.',
        'rating.min'           => 'Minimum rating is 0.5.',
        'rating.max'           => 'Maximum rating is 5.',
        'comment.required'     => 'Please write a short comment.',
        'image.image'          => 'Upload a valid image file.',
        'image.max'            => 'Image must be smaller than 4MB.',
    ]);

    $path = null;
    if ($request->hasFile('image')) {
        $path = $request->file('image')->store('review-images', 'public');
    }

    $product->reviews()->create([
        'user_id'     => null,
        'author_name' => $data['author_name'],
        'author_email'=> $data['author_email'] ?? null,
        'rating'      => $data['rating'],
        'comment'     => $data['comment'] ?? null,
        'image_path'  => $path,
        'status'      => 'pending', // or 'approved' if auto-approve
    ]);

    return back()->with('success', 'Thanks! Your review was submitted' . (config('app.moderate_reviews', true) ? ' and is awaiting approval.' : '.'));
}

}

