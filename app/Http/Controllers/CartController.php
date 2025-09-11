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
    $product = Product::with('images')->findOrFail($id);

    // Read selected options from form
    $selectedColor = $request->input('color_code'); // e.g. "#000000"
    $selectedSize  = $request->input('size');       // e.g. "M"

    // Choose a thumbnail (either color-matched or fallback to first)
    $thumbnail = $product->images
        ->firstWhere('color_code', $selectedColor)
        ?? $product->images->first();

    // Build a unique key for this cart item
    $cartKey = $id . '|' . ($selectedColor ?: 'any') . '|' . ($selectedSize ?: 'any');

    $cart = session()->get('cart', []);

    if (isset($cart[$cartKey])) {
        $cart[$cartKey]['quantity'] += (int) $request->input('quantity', 1);
    } else {
        $cart[$cartKey] = [
            "product_id" => $id,
            "name"       => $product->name,
            "price"      => $product->price,
            "image_path" => $thumbnail?->image_path,
            "quantity"   => (int) $request->input('quantity', 1),
            "color"      => $selectedColor,
            "size"       => $selectedSize,
        ];
    }

    session()->put('cart', $cart);
    return redirect()->route('cart.index')->with('success', 'Product added to cart!');
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
    if (!is_null($promo->usage_limit) && (int)$promo->used_count >= (int)$promo->usage_limit) {
        return back()->withErrors(['promo_code' => 'This promo code has reached its usage limit.']);
    }

    // Current cart total
    $cart = session('cart', []);
    $total = collect($cart)->sum(fn ($item) => ((float)$item['price']) * ((int)$item['quantity']));

    // Min order check
    if (!is_null($promo->min_order_amount) && $total < (float)$promo->min_order_amount) {
        return back()->withErrors([
            'promo_code' => 'Order must be at least ' . number_format((float)$promo->min_order_amount, 2) . ' to use this promo.',
        ]);
    }

    // Already applied?
    $promos = session('promos', []); // array keyed by code or flat list—your view loops it safely
    if (isset($promos[$promo->code])) {
        return back()->with('success', "Promo code {$promo->code} is already applied.");
    }

    // Rule: allow only 1 shipping + 1 (fixed|percentage)
    $hasShipping = collect($promos)->contains(fn ($p) => ($p['discount_type'] ?? null) === 'shipping');
    $hasDiscount = collect($promos)->contains(fn ($p) => in_array(($p['discount_type'] ?? ''), ['fixed', 'percentage'], true));

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
        'code'           => $promo->code,
        'discount_type'  => $promo->discount_type,          // 'shipping' | 'fixed' | 'percentage'
        'value'          => (float)$promo->value,           // for fixed/percentage display
        'min_order'      => $promo->min_order_amount ? (float)$promo->min_order_amount : null,
        'expires_at'     => $promo->expires_at?->toDateTimeString(),
        // Optional helpers for UI:
        'percent'        => $promo->discount_type === 'percentage' ? (float)$promo->value : null,
        'amount'         => $promo->discount_type === 'fixed' ? (float)$promo->value : null,
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