<?php

namespace App\Http\Controllers;

use App\Models\Product;

class StoreController extends Controller
{
    public function home()
    {
        $locale = app()->getLocale();

        $products = Product::with(['images', 'category'])
            ->latest()
            ->get()
            ->groupBy(function ($p) use ($locale) {
                if (!$p->category) {
                    return 'Uncategorized';
                }

                // If Arabic locale and category->name_ar is set, use it
                if ($locale === 'ar' && !empty($p->category->name_ar)) {
                    return $p->category->name_ar;
                }

                // Otherwise fall back to default name
                return $p->category->name ?? 'Uncategorized';
            });

        return view('index', compact('products'));
    }

    public function show($id)
    {
        $product = Product::with('images')->findOrFail($id);
        return view('products.show', compact('product'));
    }
}
