<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

   public function applyCoupon(Request $request)
{
    $request->validate([
        'coupon_code' => 'required|string',
    ]);

    $coupon = Coupon::where('code', $request->coupon_code)->first();

    if (!$coupon) {
        return redirect()->back()->with('error', 'Invalid coupon code.');
    }

    session()->put('coupon', $coupon);
    return redirect()->back()->with('success', 'Coupon applied successfully!');
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