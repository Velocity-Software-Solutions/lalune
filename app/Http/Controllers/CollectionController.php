<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index()
    {
        $products = Product::with(['images', 'collection'])
            ->latest()
            ->get()
            ->groupBy(function ($p) {
                if (!$p->collection) {
                    return 'Uncategorized';
                }

                // Otherwise fall back to default name
                return $p->collection->name ?? 'Uncategorized';
            });
        return view('collections', compact('products'));
    }
}
