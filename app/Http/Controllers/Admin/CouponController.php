<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::latest()->paginate(10);
        return view('admin.coupons', compact('coupons'));
    }

    public function create()
    {
        return view('admin.admin.coupons.create');
    }

  public function store(Request $request)
{
    $validated = $request->validate([
        'code' => 'required|string|unique:coupons,code',
        'discount_type' => 'required|in:fixed,percentage',
        'value' => 'required|numeric|min:0.01',
        'min_order_amount' => 'nullable|numeric|min:0',
        'usage_limit' => 'nullable|integer|min:1',
        'expires_at' => 'required|date|after:now',
        'is_active' => 'required|boolean'
    ]);

    Coupon::create($validated);

    return redirect()->route('admin.admin.coupons.index')->with('success', 'Coupon created successfully!');
}


    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $request->validate([
            'code' => 'required|unique:coupons,code,' . $coupon->id,
            'discount_type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric',
            'min_order_amount' => 'nullable|numeric',
            'usage_limit' => 'nullable|integer',
            'expires_at' => 'required|date',
            'is_active' => 'required|boolean',
        ]);

        $coupon->update($request->all());

        return redirect()->route('admin.admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return redirect()->route('admin.admin.coupons.index')->with('success', 'Coupon deleted.');
    }
}

