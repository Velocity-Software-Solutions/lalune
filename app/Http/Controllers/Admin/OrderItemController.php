<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'product_id'   => ['nullable','exists:products,id'],
            'product_name' => ['required_without:product_id','string','max:255'],
            'price'        => ['required','numeric','min:0'],
            'quantity'     => ['required','integer','min:1'],
        ]);

        $product = $data['product_id'] ? Product::find($data['product_id']) : null;
        $name    = $product?->name ?? $data['product_name'];
        $price   = $data['price'];
        $qty     = $data['quantity'];
        $sub     = $price * $qty;

        $order->items()->create([
            'product_id'   => $product?->id,
            'product_name' => $name,
            'price'        => $price,
            'quantity'     => $qty,
            'subtotal'     => $sub,
        ]);

        $order->recalcTotals();

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, Order $order, OrderItem $orderItem)
    {
        abort_unless($orderItem->order_id === $order->id, 404);

        $data = $request->validate([
            'price'    => ['required','numeric','min:0'],
            'quantity' => ['required','integer','min:1'],
        ]);

        $sub = $data['price'] * $data['quantity'];
        $orderItem->update([
            'price'    => $data['price'],
            'quantity' => $data['quantity'],
            'subtotal' => $sub,
        ]);

        $order->recalcTotals();

        return back()->with('success', 'Item updated.');
    }

    public function destroy(Order $order, OrderItem $orderItem)
    {
        abort_unless($orderItem->order_id === $order->id, 404);

        $orderItem->delete();
        $order->recalcTotals();

        return back()->with('success', 'Item removed.');
    }
}