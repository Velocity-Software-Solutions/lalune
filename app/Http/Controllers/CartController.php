<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Coupon;
use Barryvdh\DomPDF\Facade\Pdf;
class CartController extends Controller
{
    public function index()
    {
        return view('cart.index');
    }

    public function add(Request $request, $id)
    {
        $product = Product::with(['colors', 'sizes', 'stock', 'images'])->findOrFail($id);

        // Inputs (IDs preferred; fall back to legacy fields)
        $colorIdIn = $request->filled('color_id') ? (int) $request->input('color_id') : null;
        $sizeIdIn = $request->filled('size_id') ? (int) $request->input('size_id') : null;

        $selectedColorHex = trim((string) $request->input('color_code', ''));
        $selectedColorHex = $selectedColorHex !== '' ? strtoupper($selectedColorHex) : null; // '#RRGGBB' or null
        $selectedSizeStr = trim((string) $request->input('size', '')) ?: null;

        $requestedQty = max(1, (int) $request->input('quantity', 1));

        $hasColors = $product->colors->isNotEmpty();
        $hasSizes = $product->sizes->isNotEmpty();

        // Require selections if those axes exist
        if ($hasColors && !$colorIdIn && !$selectedColorHex) {
            return back()->withErrors(['color_code' => 'Please choose a color.']);
        }
        if ($hasSizes && !$sizeIdIn && !$selectedSizeStr) {
            return back()->withErrors(['size' => 'Please choose a size.']);
        }

        // Resolve color_id to a valid product color
        $colorId = null;
        if ($hasColors) {
            if ($colorIdIn) {
                $ok = $product->colors->contains('id', $colorIdIn);
                if (!$ok) {
                    return back()->withErrors(['color_code' => 'Selected color is not available for this product.']);
                }
                $colorId = $colorIdIn;
            } elseif ($selectedColorHex) {
                $colorId = optional($product->colors->firstWhere('color_code', strtoupper($selectedColorHex)))->id;
                if (!$colorId) {
                    return back()->withErrors(['color_code' => 'Selected color is not available for this product.']);
                }
            }
        }

        // Resolve size_id to a valid product size
        $sizeId = null;
        if ($hasSizes) {
            if ($sizeIdIn) {
                $ok = $product->sizes->contains('id', $sizeIdIn);
                if (!$ok) {
                    return back()->withErrors(['size' => 'Selected size is not available for this product.']);
                }
                $sizeId = $sizeIdIn;
            } elseif ($selectedSizeStr) {
                $sizeId = optional($product->sizes->firstWhere('size', $selectedSizeStr))->id;
                if (!$sizeId) {
                    return back()->withErrors(['size' => 'Selected size is not available for this product.']);
                }
            }
        }

        // Human-friendly labels (for display in cart)
        $resolvedColorHex = $selectedColorHex
            ?: optional($product->colors->firstWhere('id', $colorId))->color_code;
        $resolvedColorHex = $resolvedColorHex ? strtoupper($resolvedColorHex) : null;

        $resolvedSizeStr = $selectedSizeStr
            ?: optional($product->sizes->firstWhere('id', $sizeId))->size;

        // Find matching variant row when any axis exists
        $variant = null;
        if ($hasColors || $hasSizes) {
            $variant = $product->stock->first(function ($row) use ($colorId, $sizeId, $hasColors, $hasSizes) {
                $colorMatch = $hasColors ? ((int) $row->color_id === (int) $colorId) : is_null($row->color_id);
                $sizeMatch = $hasSizes ? ((int) $row->size_id === (int) $sizeId) : is_null($row->size_id);
                return $colorMatch && $sizeMatch;
            });

            if (!$variant) {
                return back()->withErrors(['quantity' => 'Sorry, this option combination is unavailable.']);
            }
        }

        // Available quantity (optionally subtract active reservations)
        if ($variant) {
            $available = (int) $variant->quantity_on_hand;

            // If you use reservations, uncomment this block:
            // $reserved = \DB::table('stock_reservations')
            //     ->where('product_stock_id', $variant->id)
            //     ->where('status', true)
            //     ->where('expires_at', '>', now())
            //     ->sum('quantity');
            // $available = max(0, $available - (int) $reserved);
        } else {
            $available = (int) $product->stock_quantity;

            // If you use product-level reservations, uncomment:
            // $reserved = \DB::table('stock_reservations')
            //     ->where('product_id', $product->id)
            //     ->whereNull('product_stock_id')
            //     ->where('status', true)
            //     ->where('expires_at', '>', now())
            //     ->sum('quantity');
            // $available = max(0, $available - (int) $reserved);
        }

        // Key the cart line by IDs (stable)
        $cartKey = $id . '|' . ($colorId ?: 'any') . '|' . ($sizeId ?: 'any');

        $cart = session()->get('cart', []);
        $alreadyInCart = isset($cart[$cartKey]) ? (int) $cart[$cartKey]['quantity'] : 0;

        $canStillAdd = max(0, $available - $alreadyInCart);
        if ($canStillAdd <= 0) {
            return back()->withErrors(['quantity' => 'This selection is out of stock or already at the limit in your cart.']);
        }

        $willAdd = min($requestedQty, $canStillAdd);

        // Price (respect discount if present)
        $unitPrice = $product->discount_price !== null && $product->discount_price >= 0
            ? (float) $product->discount_price
            : (float) $product->price;

        // Thumbnail (prefer color-matched)
        $thumbnail = null;
        if ($resolvedColorHex) {
            $thumbnail = $product->images->firstWhere('color_code', $resolvedColorHex);
        }
        if (!$thumbnail) {
            $thumbnail = $product->images->firstWhere('thumbnail', true) ?? $product->images->first();
        }

        // Upsert cart line (store both IDs and labels)
        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $willAdd;
            $cart[$cartKey]['price'] = $unitPrice;
            $cart[$cartKey]['image_path'] = $thumbnail?->image_path;
            $cart[$cartKey]['color_id'] = $colorId;         // NEW
            $cart[$cartKey]['size_id'] = $sizeId;          // NEW
            $cart[$cartKey]['color'] = $resolvedColorHex; // for display
            $cart[$cartKey]['size'] = $resolvedSizeStr;  // for display
            if ($variant && empty($cart[$cartKey]['product_stock_id'])) {
                $cart[$cartKey]['product_stock_id'] = $variant->id;
            }
        } else {
            $cart[$cartKey] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $unitPrice,
                'image_path' => $thumbnail?->image_path,
                'quantity' => $willAdd,
                'color_id' => $colorId,          // NEW
                'size_id' => $sizeId,           // NEW
                'color' => $resolvedColorHex, // display
                'size' => $resolvedSizeStr,  // display
                'product_stock_id' => $variant->id ?? null,
            ];
        }

        session()->put('cart', $cart);

        if ($willAdd < $requestedQty) {
            return redirect()
                ->route('cart.index')
                ->with('warning', "Only {$canStillAdd} in stock for this selection. Added {$willAdd} to your cart.");
        }

        return redirect()
            ->route('cart.index')
            ->with('success', 'Product added to cart!');
    }


    public function update(Request $request, $cartKey)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] = max(1, (int) $request->quantity);
            session()->put('cart', $cart);
        }

        return redirect()->route('cart.index');
    }


    public function remove($id)
    {
        $cart = session()->get('cart', []);
        if (isset($cart[$id])) {
            unset($cart[$id]);
            session()->put('cart', $cart);
        }
        return redirect()->route('cart.index');
    }

    public function applyPromo(Request $request)
    {
        $request->validate([
            'promo_code' => 'required|string',
        ]);

        $code = strtolower(trim($request->promo_code));

        /** @var PromoCode|null $promo */
        $promo = PromoCode::whereRaw('LOWER(code) = ?', [$code])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();

        if (!$promo) {
            return back()->withErrors(['promo_code' => 'Invalid or expired promo code.']);
        }

        // Enforce global usage cap: usage_limit (nullable) vs used_count
        if (!is_null($promo->usage_limit) && (int) $promo->used_count >= (int) $promo->usage_limit) {
            return back()->withErrors(['promo_code' => 'This promo code has reached its usage limit.']);
        }

        // Current cart total
        $cart = session('cart', []);
        $total = collect($cart)->sum(fn($item) => ((float) $item['price']) * ((int) $item['quantity']));

        // Min order check
        if (!is_null($promo->min_order_amount) && $total < (float) $promo->min_order_amount) {
            return back()->withErrors([
                'promo_code' => 'Order must be at least ' . number_format((float) $promo->min_order_amount, 2) . ' to use this promo.',
            ]);
        }

        // Already applied?
        $promos = session('promos', []); // array keyed by code or flat list—your view loops it safely
        if (isset($promos[$promo->code])) {
            return back()->with('success', "Promo code {$promo->code} is already applied.");
        }

        // Rule: allow only 1 shipping + 1 (fixed|percentage)
        $hasShipping = collect($promos)->contains(fn($p) => ($p['discount_type'] ?? null) === 'shipping');
        $hasDiscount = collect($promos)->contains(fn($p) => in_array(($p['discount_type'] ?? ''), ['fixed', 'percentage'], true));

        if ($promo->discount_type === 'shipping') {
            if ($hasShipping) {
                return back()->withErrors(['promo_code' => 'A shipping promo is already applied.']);
            }
        } else { // fixed | percentage
            if ($hasDiscount) {
                return back()->withErrors(['promo_code' => 'A discount promo is already applied.']);
            }
        }

        // Store a lean snapshot of the promo in session (don’t store sensitive/internal columns)
        $promos[$promo->code] = [
            'code' => $promo->code,
            'discount_type' => $promo->discount_type,          // 'shipping' | 'fixed' | 'percentage'
            'value' => (float) $promo->value,           // for fixed/percentage display
            'min_order' => $promo->min_order_amount ? (float) $promo->min_order_amount : null,
            'expires_at' => $promo->expires_at?->toDateTimeString(),
            // Optional helpers for UI:
            'percent' => $promo->discount_type === 'percentage' ? (float) $promo->value : null,
            'amount' => $promo->discount_type === 'fixed' ? (float) $promo->value : null,
            // Note: usage_count not modified here; increment only on successful order creation
        ];

        session(['promos' => $promos]);

        return back()->with('success', "Promo code {$promo->code} applied.");
    }

    public function removePromo($code)
    {
        $promos = session('promos', []);
        unset($promos[$code]);
        session(['promos' => $promos]);

        return back()->with('success', "Promo code {$code} removed.");
    }


    public function downloadReceipt(Order $order)
    {
        if (auth()->check() && $order->user_id !== auth()->id()) {
            abort(403);
        }

        $pdf = Pdf::loadView('checkout.receipt', ['order' => $order]);
        return $pdf->download('Receipt-' . $order->order_number . '.pdf');
    }
}