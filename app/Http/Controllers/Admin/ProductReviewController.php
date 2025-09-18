<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // null|pending|approved|rejected
        $q = trim((string) $request->query('q', ''));

        $reviews = ProductReview::query()
            ->with(['product:id,name', 'user:id,name,email'])
            ->when(
                in_array($status, ['pending', 'approved', 'rejected'], true),
                fn($q2) => $q2->where('status', $status)
            )
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('author_name', 'like', "%{$q}%")
                        ->orWhere('author_email', 'like', "%{$q}%")
                        ->orWhere('comment', 'like', "%{$q}%")
                        ->orWhereHas('product', fn($p) => $p->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reviews', compact('reviews', 'status', 'q'));
    }

    public function approve(ProductReview $review)
    {
        $review->forceFill([
            'status' => 'approved'
        ])->save();

        return back()->with('success', 'Review approved.');
    }

    public function reject(ProductReview $review)
    {
        $review->forceFill([
            'status' => 'rejected',
        ])->save();

        return back()->with('success', 'Review rejected.');
    }

    public function destroy(ProductReview $review)
    {
        $review->delete();
        return back()->with('success', 'Review deleted.');
    }
}
