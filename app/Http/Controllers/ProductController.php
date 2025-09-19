<?php

namespace App\Http\Controllers;

use App\Models\Category;
use DB;
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

        $products = Product::where(['category_id' => $categoryId, 'status' => 1])
            ->with('images')
            ->latest()
            ->get();

        return view('products.index', compact('products', 'category'));
    }


    public function show($slug)
    {
        $product = Product::where('slug', $slug)->with(['images', 'colors', 'sizes', 'stock', 'approvedReviews'])->first();
        if (!$product) {
            $product = Product::with(['images', 'colors', 'sizes', 'stock', 'approvedReviews'])->find($slug);
            if (!$product) {
                abort(404);
            }
        }
        // Similar products
        $smiliarProducts = Product::where('category_id', $product->category_id)
            ->where('status', 1)
            ->whereKeyNot($product->getKey())
            ->with('images')
            ->limit(4)
            ->get();

        $now = now();

        // ===== Variant-level reservations (by product_stock_id) =====
        $stockIds = $product->stock->pluck('id');

        $reservedByStockId = $stockIds->isEmpty()
            ? collect()
            : DB::table('stock_reservations')
                ->select('product_stock_id', DB::raw('SUM(quantity) as reserved'))
                ->whereIn('product_stock_id', $stockIds)
                ->where('status', true)               // active
                ->where('expires_at', '>', $now)      // not expired
                ->groupBy('product_stock_id')
                ->pluck('reserved', 'product_stock_id'); // [stock_id => reserved_sum]

        // Attach computed available quantity to each stock row
        $product->stock->transform(function ($row) use ($reservedByStockId) {
            $reserved = (int) ($reservedByStockId[$row->id] ?? 0);
            $row->available_qty = max(0, (int) $row->quantity_on_hand - $reserved);
            return $row;
        });

        // ===== Product-level reservations (no variants) =====
        $productTotalQty = null;
        if ($product->colors->isEmpty() && $product->sizes->isEmpty()) {
            $productLevelReserved = (int) DB::table('stock_reservations')
                ->where('product_id', $product->id)
                ->whereNull('product_stock_id')
                ->where('status', true)
                ->where('expires_at', '>', $now)
                ->sum('quantity');

            // fallback to product->stock_quantity when there are no variant rows
            $productTotalQty = max(0, (int) $product->stock_quantity - $productLevelReserved);
        }

        return view('products.show', compact('product', 'smiliarProducts', 'productTotalQty'));
    }
}
