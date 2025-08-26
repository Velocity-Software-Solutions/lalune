<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf; // at the top of the file


class CheckoutController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        $coupon = session('coupon');

        $countries = [
            'UAE' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'],
            'Saudi Arabia' => ['Riyadh', 'Jeddah', 'Mecca', 'Medina', 'Dammam', 'Khobar', 'Dhahran', 'Tabuk', 'Abha', 'Hail'],
            'Kuwait' => ['Kuwait City', 'Salmiya', 'Hawally', 'Farwaniya', 'Jahra', 'Fahaheel', 'Mangaf', 'Sabah Al Salem', 'Mahboula', 'Abu Halifa'],
            'Qatar' => ['Doha', 'Al Rayyan', 'Umm Salal', 'Al Wakrah', 'Al Khor', 'Al Daayen', 'Al Shamal', 'Al Shahaniya'],
            'Oman' => ['Muscat', 'Salalah', 'Sohar', 'Nizwa', 'Sur', 'Ibri', 'Barka', 'Rustaq'],
            'Bahrain' => ['Manama', 'Muharraq', 'Riffa', 'Isa Town', 'Sitra', 'Budaiya', 'Hamad Town', 'A\'ali'],
        ];

        $shippingOptions = ShippingOptions::where('status',1)->with('cities')->get();

        $subtotal = array_reduce($cart, fn($carry, $item) => $carry + ($item['price'] * $item['quantity']), 0);
        $discount = $coupon
            ? ($coupon->discount_type === 'percentage'
                ? ($coupon->value / 100) * $subtotal
                : $coupon->value)
            : 0;

        $total = max(0, $subtotal - $discount);

        return view('checkout.index', compact('cart', 'total', 'coupon', 'discount', 'subtotal', 'countries', 'shippingOptions'));
    }

    public function process(Request $request)
    {


        $request->validate([
            'email' => auth()->check() ? 'nullable|email' : 'required|email',
            'full_name' => 'required|string|max:255',
            'country' => 'required|string',
            'city' => 'required|string',
            'shipping_option_id' => 'required|exists:shipping_options,id',
            'shipping_cost' => 'required|numeric',
            'shipping_address' => 'required|string',
            'billing_address' => 'nullable|string',
        ]);
    //   die('This code is incomplete and should not be executed.');
        $cart = session('cart', []);
        $coupon = session('coupon');

        if (empty($cart)) {
            return redirect()->route('checkout.index')->with('error', 'Your cart is empty.');
        }

        $email = auth()->check() ? auth()->user()->email : $request->email;
        if (!auth()->check()) {
            session(['guest_email' => $email]); // Optional: remember for UX
        }

        $subtotal = array_reduce($cart, fn($carry, $item) => $carry + ($item['price'] * $item['quantity']), 0);
        $discount = $coupon
            ? ($coupon->discount_type === 'percentage'
                ? ($coupon->value / 100) * $subtotal
                : $coupon->value)
            : 0;

        $total = max(0, $subtotal - $discount + $request->shipping_cost);

        $order = Order::create([
            'user_id' => auth()->id(), // nullable if guest
            'email' => $email,
            'full_name' => $request->full_name,
            'total_amount' => $total,
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
            'shipping_address' => $request->shipping_address,
            'billing_address' => $request->billing_address,
            'payment_method' => 'manual',
            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'coupon_id' => $coupon->id ?? null,
            'shipping_option_id' => $request->shipping_option_id,
            'country' => $request->country,
            'city' => $request->city,
        ]);

        foreach ($cart as $productId => $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $productId,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price'],
            ]);
        }

        Mail::to($email)->send(new \App\Mail\OrderConfirmationMail($order));

        session()->forget(['cart', 'coupon']);

        return redirect()->route('checkout.confirmation', $order->id)
            ->with('success', 'Order placed successfully!');
    }

    public function confirmation(Order $order)
    {
        if (auth()->check() && $order->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access.');
        }

        return view('checkout.confirmation', compact('order'));
    }

    public function downloadReceipt(Order $order)
{
    // Optional: restrict access to owner
    if (auth()->check() && $order->user_id !== auth()->id()) {
        abort(403, 'Unauthorized access to receipt.');
    }

    // Generate PDF from Blade view
  $pdf = Pdf::loadView('checkout.receipt', compact('order'));

    return $pdf->download("receipt-{$order->order_number}.pdf");
}
}
