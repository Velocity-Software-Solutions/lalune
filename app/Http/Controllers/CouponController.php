<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Support\Facades\Session;
use App\Models\ShippingOptions;

class CouponController extends Controller
{
    /**
     * Apply a coupon code to the session.
     */
    public function apply(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string'
        ]);

        $coupon = Coupon::where('code', $request->coupon_code)->first();

        if (!$coupon) {
            return back()->withErrors(['coupon_code' => 'Invalid coupon code.']);
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return back()->withErrors(['coupon_code' => 'This coupon has expired.']);
        }

        $subtotal = session('cart_subtotal', 0); // Ensure you store this during cart calculation

        if ($coupon->min_order && $subtotal < $coupon->min_order) {
            return back()->withErrors(['coupon_code' => 'Minimum order for this coupon is $' . number_format($coupon->min_order, 2)]);
        }

        session(['coupon' => $coupon]);

        return back()->with('success', 'Coupon applied successfully!');
    }

    /**
     * Remove the coupon from the session.
     */
    public function remove()
    {
        session()->forget('coupon');
        return back()->with('success', 'Coupon removed.');
    }



public function showCheckout()
{
    // Example: Fetch cart items from session or DB
    $cartItems = session('cart', []); // or fetch from a Cart model/table

    // Optional: calculate subtotal
    $subtotal = collect($cartItems)->sum(function ($item) {
        return $item['price'] * $item['quantity'];
    });

    session(['cart_subtotal' => $subtotal]); // Save for coupon validation

    // Sample default shipping option
    $shippingOption = ShippingOptions::first();

    return view('checkout', [
        'cartItems' => $cartItems,
        'subtotal' => $subtotal,
        'shippingOption' => $shippingOption,
        'discount' => session('coupon') ? calculateDiscount(session('coupon'), $subtotal) : 0,
        'total' => $subtotal - (session('coupon') ? calculateDiscount(session('coupon'), $subtotal) : 0) + ($shippingOption->price ?? 0),
    ]);
}

}