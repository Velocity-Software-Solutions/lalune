<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
public function index(Request $request)
{
    $categoryId = $request->query('category');

    if (!$categoryId) {
        return redirect()->route('home');
    }

    $category = Category::find($categoryId);
    if (!$category) {
        return redirect()->route('home');
    }

    $products = Product::where(['category_id'=> $categoryId,'status' => 1])
        ->with('images')
        ->latest()
        ->get();

    return view('products.index', compact('products', 'category'));
}


    public function show($id)
    {
        $product = Product::with(['images', 'colors', 'sizes'])->findOrFail($id);
        $smiliarProducts = Product::where('category_id', $product->category_id)->whereKeyNot($product->getKey())->with('images')->limit(4)->get();
        return view('products.show', compact('product', 'smiliarProducts'));
    }
}
