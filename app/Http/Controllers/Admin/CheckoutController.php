<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NewOrder;


class CheckoutController extends Controller
{
    public function show()
    {
        return view('checkout');
    }

 public function process(Request $request)
{
    $request->validate([
        'shipping_street' => 'required|string',
        'shipping_city' => 'required|string',
        'shipping_state' => 'required|string',
        'shipping_zip' => 'required|string',
        'shipping_country' => 'required|string',
        'email' => 'required|email',
        'phone' => 'required|string',
        'payment_method' => 'required|in:stripe,paypal,cod',
        'coupon_id' => 'nullable|exists:coupons,id',
        'shipping_option_id' => 'required|exists:shipping_options,id',
        'notes' => 'nullable|string',
    ]);

    // Billing address validation if not same as shipping
    if (!$request->has('same_as_shipping')) {
        $request->validate([
            'billing_street' => 'required|string',
            'billing_city' => 'required|string',
            'billing_state' => 'required|string',
            'billing_zip' => 'required|string',
            'billing_country' => 'required|string',
        ]);
    }

    $userId = auth()->id();

    // Save to `new_orders` table
    NewOrder::create([
        'user_id' => $userId,
        'shipping_street' => $request->shipping_street,
        'shipping_city' => $request->shipping_city,
        'shipping_state' => $request->shipping_state,
        'shipping_zip' => $request->shipping_zip,
        'shipping_country' => $request->shipping_country,
        'billing_street' => $request->same_as_shipping ? $request->shipping_street : $request->billing_street,
        'billing_city' => $request->same_as_shipping ? $request->shipping_city : $request->billing_city,
        'billing_state' => $request->same_as_shipping ? $request->shipping_state : $request->billing_state,
        'billing_zip' => $request->same_as_shipping ? $request->shipping_zip : $request->billing_zip,
        'billing_country' => $request->same_as_shipping ? $request->shipping_country : $request->billing_country,
        'email' => $request->email,
        'phone' => $request->phone,
        'payment_method' => $request->payment_method,
        'coupon_code' => $request->coupon_code,
        'notes' => $request->notes,
    ]);

    // Prepare addresses as text
    $shippingAddress = "{$request->shipping_street}, {$request->shipping_city}, {$request->shipping_state}, {$request->shipping_zip}, {$request->shipping_country}";
    $billingAddress = $request->has('same_as_shipping')
        ? $shippingAddress
        : "{$request->billing_street}, {$request->billing_city}, {$request->billing_state}, {$request->billing_zip}, {$request->billing_country}";

    // Sample total amount (you should calculate this from cart/logic)
    $totalAmount = 100.00;

    // Save to `orders` table
    Order::create([
        'user_id' => $userId,
        'order_number' => strtoupper(Str::random(10)),
        'total_amount' => $totalAmount,
        'payment_status' => 'pending',
        'order_status' => 'pending',
        'shipping_address' => $shippingAddress,
        'billing_address' => $billingAddress,
        'payment_method' => $request->payment_method,
        'notes' => $request->notes,
        'coupon_id' => $request->coupon_id,
        'shipping_option_id' => $request->shipping_option_id,
    ]);

    return redirect()->route('checkout.show')->with('success', 'Order placed successfully.');
}
}
