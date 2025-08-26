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
        $thumbnail = $product->images->first();
        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] += 1;
        } else {
            $cart[$id] = [
                "name" => $product->name,
                "price" => $product->price,
                "image_path" => $thumbnail->image_path,
                "quantity" => 1,
            ];
        }

        session()->put('cart', $cart);
        return redirect()->route('cart.index')->with('success', 'Product added to cart!');
    }

    public function update(Request $request, $id)
    {
        $cart = session()->get('cart', []);
        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = (int) $request->quantity;
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