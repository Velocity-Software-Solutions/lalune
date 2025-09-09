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
            ->whereHas('collection', function ($q) {
                $q->where('status', 1);
            })
            ->latest()
            ->get()
            ->groupBy(function ($p) {
                if (!$p->collection) {
                    return 'Uncategorized';
                }
                return $p->collection->name ?? 'Uncategorized';
            });

        return view('collections', compact('products'));

    }
}
