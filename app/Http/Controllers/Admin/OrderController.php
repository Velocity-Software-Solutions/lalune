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
use Str;

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
        $customers = User::orderBy('name')->get(['id', 'name', 'email']);
        $products = Product::orderBy('name')->get(['id', 'name', 'price']);
        $coupons = Coupon::orderBy('code')->get(['id', 'code', 'discount_type', 'value']);

        return view('admin.orders.create', compact('customers', 'products', 'coupons'));
    }

    /* --------------------------------------------------------------
     | Store: persist order + items
     |-------------------------------------------------------------- */

public function store(Request $request)
{
    // ---------- Validate ----------
    $validated = $request->validate([
        // customer mode
        'customer_mode'         => 'required|in:existing,new',

        // existing OR new must provide an email
        'email'                 => 'required|email:rfc,dns',
        'name'                  => 'nullable|string|max:255',

        // links (optional)
        'user_id'               => 'nullable|integer|exists:users,id',

        // addresses
        'shipping_address'      => 'required|string|max:5000',
        'billing_address'       => 'nullable|string|max:5000',

        // money/meta
        'payment_method'        => 'required|string|max:100',
        'coupon_id'             => 'nullable|integer|exists:coupons,id',
        'notes'                 => 'nullable|string|max:5000',

        // items
        'items'                 => 'required|array|min:1',
        'items.*.product_id'    => 'nullable|integer|exists:products,id',
        'items.*.product_name'  => 'nullable|string|max:255',
        'items.*.price'         => 'required|numeric|min:0',
        'items.*.quantity'      => 'required|integer|min:1',
    ]);

    // Normalize currency (set yours here)
    $currency = strtoupper(config('app.currency', 'USD'));

    // ---------- Resolve/normalize customer ----------
    $email = trim($validated['email']);
    $name  = trim((string)($validated['name'] ?? ''));

    $userId = null;

    if ($validated['customer_mode'] === 'existing') {
        // If a user exists with that email, attach it; otherwise leave null (guest-by-email).
        $existing = User::where('email', $email)->first();
        $userId   = $existing?->id ?? ($validated['user_id'] ?? null);
    } else { // 'new'
        // If a user already exists with this email, reuse it; else keep guest order (user_id null).
        $existing = User::where('email', $email)->first();
        $userId   = $existing?->id ?? null;
        // Optionally: create a user here if you want
        // if (!$existing) { $user = User::create([...]); $userId = $user->id; }
    }

    // ---------- Build line items + totals ----------
    $lineItems = [];
    $subtotalCents = 0;

    foreach ($validated['items'] as $row) {
        $qty   = (int) $row['quantity'];
        $price = (float) $row['price'];              // dollars
        $cents = (int) round($price * 100);          // to cents

        $productId   = $row['product_id'] ?? null;
        $productName = trim((string)($row['product_name'] ?? ''));

        $resolvedName = $productName;
        $sku          = null;
        $productThumb = null;

        if ($productId) {
            $product = Product::with('images')->find($productId);
            if ($product) {
                // If admin left product_name blank, use product's
                if ($resolvedName === '') {
                    $resolvedName = $product->name;
                }
                $sku          = $product->sku ?? null;
                $productThumb = $product->images->first()?->image_path;
            }
        }

        $rowSubtotal = $cents * $qty;
        $subtotalCents += $rowSubtotal;

        $lineItems[] = [
            'product_id'        => $productId,
            'name'              => $resolvedName !== '' ? $resolvedName : 'Custom Item',
            'sku'               => $sku,
            'quantity'          => $qty,
            'unit_price_cents'  => $cents,
            'subtotal_cents'    => $rowSubtotal,
            'discount_cents'    => 0,
            'tax_cents'         => 0,
            'total_cents'       => $rowSubtotal,  // no tax/discount here; adjust below if you add them
            'currency'          => $currency,
            'snapshot'          => [
                'image_url' => $productThumb ? asset('storage/'.$productThumb) : null,
            ],
        ];
    }

    // If you want to calculate shipping/tax/discount server-side, do it here.
    // For now, keep them 0 and rely on coupon/shipping rules later if needed.
    $discountCents = 0;
    $shippingCents = 0;
    $taxCents      = 0;
    $totalCents    = max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents);

    // ---------- Addresses as JSON (new schema style) ----------
    $shippingJson = ['raw' => $validated['shipping_address']];
    $billingJson  = ['raw' => $validated['billing_address'] ?? $validated['shipping_address']];

    // ---------- Persist ----------
    return DB::transaction(function () use (
        $request, $validated, $userId, $name, $email,
        $currency, $subtotalCents, $discountCents, $shippingCents, $taxCents, $totalCents,
        $shippingJson, $billingJson, $lineItems
    ) {
        $order = Order::create([
            'user_id'               => $userId, // nullable
            'coupon_id'             => $validated['coupon_id'] ?? null,

            'order_number'          => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),

            'stripe_payment_intent' => null,
            'stripe_customer_id'    => null,

            'full_name'             => $name ?: $email,
            'email'                 => $email,
            'phone'                 => null,

            'currency'              => $currency,
            'subtotal_cents'        => $subtotalCents,
            'discount_cents'        => $discountCents,
            'shipping_cents'        => $shippingCents,
            'tax_cents'             => $taxCents,
            'total_cents'           => $totalCents,

            // Admin-created: usually pending payment or paid manually
            'payment_status'        => 'pending',
            'order_status'          => 'processing',
            'payment_method'        => $validated['payment_method'],

            'paid_at'               => null,

            'shipping_address_json' => $shippingJson,
            'billing_address_json'  => $billingJson,

            'coupon_code'           => optional(Coupon::find($validated['coupon_id'] ?? null))->code,
            'ip_address'            => $request->ip(),
            'user_agent'            => substr((string) $request->userAgent(), 0, 1000),

            'snapshot'              => null, // optional: you can store a summary here
            'metadata'              => ['source' => 'admin'],
            'notes'                 => $validated['notes'] ?? null,
        ]);

        foreach ($lineItems as $li) {
            OrderItem::create([
                'order_id'          => $order->id,
                'product_id'        => $li['product_id'],
                'name'              => $li['name'],
                'sku'               => $li['sku'] ?? null,
                'quantity'          => $li['quantity'],
                'unit_price_cents'  => $li['unit_price_cents'],
                'subtotal_cents'    => $li['subtotal_cents'],
                'discount_cents'    => $li['discount_cents'],
                'tax_cents'         => $li['tax_cents'],
                'total_cents'       => $li['total_cents'],
                'currency'          => $li['currency'],
                'snapshot'          => $li['snapshot'] ?? null,
            ]);
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order created successfully.');
    });
}


    /* --------------------------------------------------------------
     | Show: order detail
     |-------------------------------------------------------------- */
    public function show(Order $order)
    {
        // $order->load(['user','items','shippingOption','coupon']);
        $order->load(['user', 'items.product', 'coupon']);

        return view('admin.orders.show', compact('order'));
    }

    /* --------------------------------------------------------------
     | Edit: order admin edit form
     |   - Update shipping addr, statuses, payment status, shipping option
     |   - We do NOT edit line items here (separate UI) to prevent mistakes
     |-------------------------------------------------------------- */
    public function edit(Order $order)
    {
        $order->load(['user', 'items.product']);

        // Load products for dropdowns in edit form

        $products = Product::with('images')->orderBy('name')->get(['id', 'name', 'price']);

        // Ensure order items are loaded with product details
        foreach ($order->items as $item) {
            $item->product; // eager load product for each item
        }
        return view('admin.orders.edit', compact('order', 'products'));

    }

    /* --------------------------------------------------------------
     | Update: status + shipping + payment
     |-------------------------------------------------------------- */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'required|in:pending,paid,failed,refunded',
            'notes' => 'nullable|string',

            // addresses
            'ship' => 'array',
            'bill' => 'array',

            // money (dollars)
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',

            // items
            'items' => 'array',
            'items.*.id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0', // dollars
            'items.*.color' => 'nullable|string|max:50',
            'items.*.size' => 'nullable|string|max:50',
        ]);

        // Update order-level simple fields
        $order->order_status = $validated['order_status'];
        $order->payment_status = $validated['payment_status'];
        $order->notes = $validated['notes'] ?? null;

        // Update addresses JSON
        $ship = $request->input('ship', []);
        $bill = $request->input('bill', []);
        $order->shipping_address_json = $ship;
        $order->billing_address_json = $bill;

        // Update line items and recalc totals
        $subtotal = 0;
        foreach ($validated['items'] as $row) {
            /** @var \App\Models\OrderItem $item */
            $item = $order->items()->whereKey($row['id'])->firstOrFail();

            $qty = (int) $row['quantity'];
            $unitCents = (int) round(($row['unit_price'] ?? 0) * 100);
            $subCents = $qty * $unitCents;

            // snapshot: keep existing, override color/size if provided
            $snap = is_array($item->snapshot) ? $item->snapshot : [];
            if (!empty($row['color']))
                $snap['color'] = $row['color'];
            if (!empty($row['size']))
                $snap['size'] = $row['size'];

            $item->quantity = $qty;
            $item->unit_price_cents = $unitCents;
            $item->subtotal_cents = $subCents;

            // Keep discount/tax per-line 0 unless you implement proration
            $item->discount_cents = $item->discount_cents ?? 0;
            $item->tax_cents = $item->tax_cents ?? 0;
            $item->total_cents = $subCents - ($item->discount_cents ?? 0) + ($item->tax_cents ?? 0);
            $item->snapshot = $snap;
            $item->save();

            $subtotal += $subCents;
        }

        // Order totals
        $discount = (int) round(($validated['discount_amount'] ?? 0) * 100);
        $shipping = (int) round(($validated['shipping_amount'] ?? 0) * 100);
        $tax = (int) round(($validated['tax_amount'] ?? 0) * 100);
        $total = max(0, $subtotal - $discount + $shipping + $tax);

        $order->subtotal_cents = $subtotal;
        $order->discount_cents = $discount;
        $order->shipping_cents = $shipping;
        $order->tax_cents = $tax;
        $order->total_cents = $total;

        $order->save();

        return redirect()
            ->route('admin.orders.show', $order)
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
