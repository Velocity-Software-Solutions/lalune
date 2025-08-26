<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;

use App\Models\User;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\ShippingOptions as ShippingOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /* --------------------------------------------------------------
     | Index: list orders
     |-------------------------------------------------------------- */
    public function index()
    {
        $orders = Order::with(['user:id,name,email'])
            ->latest()
            ->paginate(20);

        return view('admin.orders.index', compact('orders'));
    }

    /* --------------------------------------------------------------
     | Create: show new order form
     |-------------------------------------------------------------- */
    public function create()
    {
        $customers       = User::orderBy('name')->get(['id','name','email']);
        $products        = Product::orderBy('name')->get(['id','name','price']);
        $coupons         = Coupon::orderBy('code')->get(['id','code','discount_type','value']);
        $shippingOptions = ShippingOption::orderBy('name')->get(['id','name','price','delivery_time']);

        return view('admin.orders.create', compact('customers','products','coupons','shippingOptions'));
    }

    /* --------------------------------------------------------------
     | Store: persist order + items
     |-------------------------------------------------------------- */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'            => ['required','exists:users,id'],
            'shipping_address'   => ['required','string'],
            'billing_address'    => ['nullable','string'],
            'payment_method'     => ['required','string','max:255'],
            'notes'              => ['nullable','string'],
            // coupon & shipping_required per your schema (NOT NULL)
            'coupon_id'          => ['required','exists:coupons,id'],
            'shipping_option_id' => ['required','exists:shipping_options,id'],

            // Line items
            'items'                  => ['required','array','min:1'],
            'items.*.product_id'     => ['nullable','exists:products,id'],
            'items.*.product_name'   => ['required_without:items.*.product_id','string','max:255'],
            'items.*.price'          => ['required','numeric','min:0'],
            'items.*.quantity'       => ['required','integer','min:1'],
        ]);

        $order = null;

        DB::transaction(function () use (&$order, $request) {
            // Create the order shell; totals set after items loop
            $order = Order::create([
                'user_id'            => $request->user_id,
                'total_amount'       => 0, // temp
                'payment_status'     => Order::PAYMENT_PENDING, // enum: pending/paid/failed
                'order_status'       => Order::ORDER_PENDING,   // enum: pending/.../cancelled
                'shipping_address'   => $request->shipping_address,
                'billing_address'    => $request->billing_address,
                'payment_method'     => $request->payment_method,
                'notes'              => $request->notes,
                'coupon_id'          => $request->coupon_id,
                'shipping_option_id' => $request->shipping_option_id,
            ]);

            $total = 0;

            foreach ($request->items as $row) {
                // If product selected, snapshot current data; else use manual values
                $product = isset($row['product_id']) && $row['product_id']
                    ? Product::find($row['product_id'])
                    : null;

                $name  = $product?->name ?? $row['product_name'] ?? 'Item';
                $price = (float) ($row['price'] ?? $product?->price ?? 0);
                $qty   = (int) ($row['quantity'] ?? 1);
                $sub   = $price * $qty;
                $total += $sub;

                $order->items()->create([
                    'product_id'   => $product?->id,
                    'product_name' => $name,
                    'price'        => $price,
                    'quantity'     => $qty,
                    'subtotal'     => $sub,
                ]);
            }

            $order->update(['total_amount' => $total]);
        });

        return redirect()->route('admin.admin.orders.show', $order)->with('success', 'Order created.');
    }

    /* --------------------------------------------------------------
     | Show: order detail
     |-------------------------------------------------------------- */
    public function show(Order $order)
    {
        // $order->load(['user','items','shippingOption','coupon']);
        $order->load(['user','items.product','shippingOption','coupon']);

        return view('admin.orders.show', compact('order'));
    }

    /* --------------------------------------------------------------
     | Edit: order admin edit form
     |   - Update shipping addr, statuses, payment status, shipping option
     |   - We do NOT edit line items here (separate UI) to prevent mistakes
     |-------------------------------------------------------------- */
public function edit(Order $order)
{
    $order->load(['user', 'items.product', 'shippingOption']);

    $shippingOptions = ShippingOption::orderBy('name')->get(['id','name','delivery_time', 'price']);

    // Load products for dropdowns in edit form

$products = Product::with('images')->orderBy('name')->get(['id', 'name','price']);

    // Ensure order items are loaded with product details
    foreach ($order->items as $item) {
        $item->product; // eager load product for each item
    }
  return view('admin.orders.edit', compact('order', 'shippingOptions', 'products'));

}

    /* --------------------------------------------------------------
     | Update: status + shipping + payment
     |-------------------------------------------------------------- */
public function update(Request $request, Order $order)
{
    DB::transaction(function () use ($request, $order) {
        $order->update($request->only([
            'shipping_address',
            'billing_address',
            'order_status',
            'payment_status',
            'shipping_option_id',
            'notes',
        ]));

        $total = 0;

        foreach ($request->items as $itemData) {
            $item = \App\Models\OrderItem::find($itemData['id']);

            if ($item) {
                $item->update([
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'subtotal' => $itemData['quantity'] * $itemData['price'],
                ]);

                $total += $item->subtotal;
            }
        }

        $shipping = $order->shippingOption;
        $total += $shipping?->price ?? 0;

        $order->update(['total_amount' => $total]);
    });

    return redirect()->route('admin.admin.orders.edit', $order)
        ->with('success', 'Order updated successfully.');
}


    /* --------------------------------------------------------------
     | Destroy: delete order + cascade items
     |-------------------------------------------------------------- */
    public function destroy(Order $order)
    {
        $order->delete();
        return redirect()->route('admin.admin.orders.index')->with('success', 'Order deleted.');
    }
}
