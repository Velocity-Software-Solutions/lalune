<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function index()
    {
        $promoCodes = PromoCode::orderByDesc('created_at')->paginate(15);

        return view('admin.promo-codes', compact('promoCodes'));
    }


public function store(Request $request)
{
    $validated = $request->validate([
        'code'             => 'required|string|max:50|unique:promo_codes,code',
        'discount_type'    => 'required|in:shipping,fixed,percentage',
        'value'            => 'nullable|numeric|min:0',
        'min_order_amount' => 'nullable|numeric|min:0',
        'usage_limit'      => 'nullable|integer|min:1',
        'expires_at'       => 'nullable|date',
        'is_active'        => 'boolean',
    ]);

    // If shipping, force value to null
    if ($validated['discount_type'] === 'shipping') {
        $validated['value'] = null;
    }

    PromoCode::create($validated);

    return redirect()->route('admin.promo-codes.index')
        ->with('success', 'Promo code created successfully!');
}

public function update(Request $request, PromoCode $promoCode)
{
    $data = $request->validate([
        'code'             => ['required','string','max:64',"unique:promo_codes,code,{$promoCode->id}"],
        'discount_type'    => ['required','in:shipping,fixed,percentage'],
        'value'            => ['nullable','numeric','min:0','required_unless:discount_type,shipping'],
        'min_order_amount' => ['nullable','numeric','min:0'],
        'usage_limit'      => ['nullable','integer','min:1'],
        'expires_at'       => ['nullable','date'],
        'is_active'        => ['required','boolean'],
    ]);

    if ($data['discount_type'] === 'shipping') {
        $data['value'] = null;
    }

    $promoCode->update($data);

    return back()->with('success','Promo code updated.');
}

    public function destroy(PromoCode $promoCode)
    {
        $promoCode->delete();
        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo Code deleted.');
    }
}

