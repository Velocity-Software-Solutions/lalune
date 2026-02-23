<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingOption;
use Illuminate\Http\Request;

class ShippingOptionController extends Controller
{
    /**
     * Display all shipping options.
     */
    public function index()
    {
        $shippingOptions = ShippingOption::where('status', 1)->with('cities')->latest()->paginate(10);
        return view('admin.shipping-options', compact('shippingOptions'));
    }

    /**
     * Store a new shipping option.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'delivery_time' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'country' => 'required|string|max:100',
            'cities' => 'required|array|min:1',
            'cities.*' => 'required|string|max:2000',
        ]);

        $shippingOption = ShippingOption::create([
            'name' => $request->name,
            'price' => $request->price,
            'delivery_time' => $request->delivery_time,
            'description' => $request->description,
            'country' => $request->country,
        ]);

        // return 

        $shippingOption->cities()->delete();

        foreach ($request->cities as $cityData) {
            $shippingOption->cities()->create([
                'city' => $cityData,
            ]);
        }

        return redirect()->back()->with('success', 'Shipping option added successfully.');
    }

    /**
     * Update an existing shipping option.
     */
    public function update(Request $request, ShippingOption $shippingOption)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'delivery_time' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'country' => 'required|string|max:100',
            'cities' => 'required|array|min:1',
            'cities.*' => 'required|string|max:2000',
        ]);


        $shippingOption->update($validated);

        $shippingOption->cities()->delete();

        foreach ($request->cities as $cityData) {
            $shippingOption->cities()->create([
                'city' => $cityData,
            ]);
        }
        return redirect()->back()->with('success', 'Shipping option updated successfully.');
    }

    /**
     * Delete a shipping option.
     */
    public function destroy(ShippingOption $shippingOption)
    {
        $shippingOption->status = 0;

        $shippingOption->save();
        return redirect()->back()->with('success', 'Shipping option deleted.');
    }
}