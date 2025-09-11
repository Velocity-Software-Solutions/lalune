<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderItem;

use App\Models\User;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\ShippingOptions as ShippingOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Mail;
use Str;

class OrderController extends Controller
{
    /* --------------------------------------------------------------
     | Index: list orders
     |-------------------------------------------------------------- */

    public function index(Request $request)
    {
        // Eager load user to avoid N+1 queries
        $orders = Order::with('user')
            ->latest()
            ->paginate(15);

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
            'customer_mode' => 'required|in:existing,new',

            // existing OR new must provide an email
            'email' => 'required|email:rfc,dns',
            'name' => 'nullable|string|max:255',

            // links (optional)
            'user_id' => 'nullable|integer|exists:users,id',

            // addresses
            'shipping_address' => 'required|string|max:5000',
            'billing_address' => 'nullable|string|max:5000',

            // money/meta
            'payment_method' => 'required|string|max:100',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'notes' => 'nullable|string|max:5000',

            // items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',

            // promos (optional – only stored in metadata)
            'promos' => 'array',
            'promos.shipping.code' => 'nullable|string|max:100',
            'promos.discount.code' => 'nullable|string|max:100',
            'promos.discount.type' => 'nullable|in:fixed,percentage',
            'promos.discount.percent' => 'nullable|numeric|min:0',
            'promos.discount.amount_cents' => 'nullable|integer|min:0',
        ]);

        // Normalize currency
        $currency = strtoupper(config('app.currency', 'USD'));

        // ---------- Resolve/normalize customer ----------
        $email = trim($validated['email']);
        $name = trim((string) ($validated['name'] ?? ''));

        $userId = null;
        if ($validated['customer_mode'] === 'existing') {
            $existing = User::where('email', $email)->first();
            $userId = $existing?->id ?? ($validated['user_id'] ?? null);
        } else {
            $existing = User::where('email', $email)->first();
            $userId = $existing?->id ?? null;
        }

        // ---------- Build line items + totals ----------
        $orderItemsForCreate = [];
        $itemsSnapshot = []; // compact snapshot like checkout (but from admin form)
        $subtotalCents = 0;

        foreach ($validated['items'] as $row) {
            $qty = (int) $row['quantity'];
            $price = (float) $row['price'];        // dollars
            $cents = (int) round($price * 100);    // to cents

            $productId = $row['product_id'] ?? null;
            $productName = trim((string) ($row['product_name'] ?? ''));
            $resolvedName = $productName;
            $sku = null;
            $productThumb = null;

            if ($productId) {
                $product = Product::with('images')->find($productId);
                if ($product) {
                    if ($resolvedName === '')
                        $resolvedName = $product->name;
                    $sku = $product->sku ?? null;
                    $productThumb = $product->images->first()?->image_path;
                }
            }

            if ($resolvedName === '')
                $resolvedName = 'Custom Item';

            $rowSubtotal = $cents * $qty;
            $subtotalCents += $rowSubtotal;

            $orderItemsForCreate[] = [
                'product_id' => $productId,
                'name' => $resolvedName,
                'sku' => $sku,
                'quantity' => $qty,
                'unit_price_cents' => $cents,
                'subtotal_cents' => $rowSubtotal,
                'discount_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => $rowSubtotal, // no tax/discount per-line here
                'currency' => $currency,
                'snapshot' => [
                    'image_url' => $productThumb ? asset('storage/' . $productThumb) : null,
                    // optional: 'variant' => ['color'=>..., 'size'=>...]
                ],
            ];

            // Add to compact items snapshot similar to Stripe pull
            $itemsSnapshot[] = [
                'description' => $resolvedName,
                'quantity' => $qty,
                'amount_subtotal' => $rowSubtotal,
                'amount_total' => $rowSubtotal,
                'currency' => $currency,
                // keep room for variants if you later add fields in the form
                'color' => null,
                'size' => null,
                'image_url' => $productThumb ? asset('storage/' . $productThumb) : null,
            ];
        }

        // Totals (admin-driven; leave discount/shipping/tax to 0 here)
        $discountCents = 0;
        $shippingCents = 0;
        $taxCents = 0;
        $totalCents = max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents);

        // ---------- Addresses JSON (new schema style) ----------
        $shippingJson = ['raw' => $validated['shipping_address']];
        $billingJson = ['raw' => $validated['billing_address'] ?? $validated['shipping_address']];

        // ---------- Build promos_applied metadata (like checkout) ----------
        $promosInput = $request->input('promos', []);
        $promosApplied = [];

        $shipCode = data_get($promosInput, 'shipping.code');
        if ($shipCode) {
            $promosApplied[] = ['code' => $shipCode, 'type' => 'shipping'];
        }

        $discCode = data_get($promosInput, 'discount.code');
        if ($discCode) {
            $promosApplied[] = [
                'code' => $discCode,
                'type' => data_get($promosInput, 'discount.type'),        // fixed|percentage
                'percent' => data_get($promosInput, 'discount.percent'),
                'amount_cents' => (int) (data_get($promosInput, 'discount.amount_cents', 0)),
            ];
        }

        // ---------- Persist ----------
        return \DB::transaction(function () use ($request, $validated, $userId, $name, $email, $currency, $subtotalCents, $discountCents, $shippingCents, $taxCents, $totalCents, $shippingJson, $billingJson, $orderItemsForCreate, $itemsSnapshot, $promosApplied) {
            $order = Order::create([
                'user_id' => $userId,
                'coupon_id' => $validated['coupon_id'] ?? null,

                'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),

                'stripe_payment_intent' => null,
                'stripe_customer_id' => null,

                'full_name' => $name ?: $email,
                'email' => $email,
                'phone' => null,

                'currency' => $currency,
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => $discountCents,
                'shipping_cents' => $shippingCents,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,

                'payment_status' => 'pending',
                'order_status' => 'processing',
                'payment_method' => $validated['payment_method'],

                'paid_at' => null,

                'shipping_address_json' => $shippingJson,
                'billing_address_json' => $billingJson,

                'coupon_code' => optional(Coupon::find($validated['coupon_id'] ?? null))->code,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),

                // snapshot & metadata (like checkout)
                'snapshot' => $itemsSnapshot,
                'metadata' => [
                    'source' => 'admin',
                    'promos_applied' => $promosApplied,
                ],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($orderItemsForCreate as $li) {
                OrderItem::create(array_merge($li, ['order_id' => $order->id]));
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
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.color' => 'nullable|string|max:50',
            'items.*.size' => 'nullable|string|max:50',

            // promos (metadata)
            'promos' => 'array',
            'promos.shipping.code' => 'nullable|string|max:100',
            'promos.discount.code' => 'nullable|string|max:100',
            'promos.discount.type' => 'nullable|in:fixed,percentage',
            'promos.discount.percent' => 'nullable|numeric|min:0',
            'promos.discount.amount_cents' => 'nullable|integer|min:0',

            // optional tracking number field if you have one on the form
            'tracking_number' => 'nullable|string|max:255',
        ]);

        // --- detect status change BEFORE we overwrite it
        $oldStatus = $order->order_status;

        // Basic fields
        $order->order_status = $validated['order_status'];
        $order->payment_status = $validated['payment_status'];
        $order->notes = $validated['notes'] ?? null;

        // Optional: store tracking number in metadata (so it’s in the emails)
        $meta = is_array($order->metadata) ? $order->metadata : [];
        if (!empty($validated['tracking_number'])) {
            $meta['tracking_number'] = $validated['tracking_number'];
        }

        // Addresses
        $order->shipping_address_json = $request->input('ship', []);
        $order->billing_address_json = $request->input('bill', []);

        // Items + subtotal
        $subtotal = 0;
        foreach ($validated['items'] as $row) {
            /** @var \App\Models\OrderItem $item */
            $item = $order->items()->whereKey($row['id'])->firstOrFail();

            $qty = (int) $row['quantity'];
            $unitCents = (int) round(($row['unit_price'] ?? 0) * 100);
            $subCents = $qty * $unitCents;

            $snap = is_array($item->snapshot) ? $item->snapshot : [];
            if (!empty($row['color']))
                $snap['color'] = $row['color'];
            if (!empty($row['size']))
                $snap['size'] = $row['size'];

            $item->quantity = $qty;
            $item->unit_price_cents = $unitCents;
            $item->subtotal_cents = $subCents;
            $item->discount_cents = $item->discount_cents ?? 0;
            $item->tax_cents = $item->tax_cents ?? 0;
            $item->total_cents = $subCents - ($item->discount_cents ?? 0) + ($item->tax_cents ?? 0);
            $item->snapshot = $snap;
            $item->save();

            $subtotal += $subCents;
        }

        // Totals
        $discount = (int) round(($validated['discount_amount'] ?? 0) * 100);
        $shipping = (int) round(($validated['shipping_amount'] ?? 0) * 100);
        $tax = (int) round(($validated['tax_amount'] ?? 0) * 100);
        $total = max(0, $subtotal - $discount + $shipping + $tax);

        $order->subtotal_cents = $subtotal;
        $order->discount_cents = $discount;
        $order->shipping_cents = $shipping;
        $order->tax_cents = $tax;
        $order->total_cents = $total;

        // Refresh compact snapshot for the order (optional)
        $currency = $order->currency ?? 'USD';
        $itemsSnapshot = $order->items->map(function ($it) use ($currency) {
            $snap = (array) ($it->snapshot ?? []);
            return [
                'description' => $it->name,
                'quantity' => (int) $it->quantity,
                'amount_subtotal' => (int) $it->subtotal_cents,
                'amount_total' => (int) $it->total_cents,
                'currency' => $currency,
                'color' => $snap['color'] ?? data_get($snap, 'variant.color'),
                'size' => $snap['size'] ?? data_get($snap, 'variant.size'),
                'image_url' => $snap['image_url'] ?? null,
            ];
        })->values()->all();

        // Update promos in metadata
        $promosInput = $request->input('promos', []);
        $promosApplied = [];
        if ($c = data_get($promosInput, 'shipping.code')) {
            $promosApplied[] = ['code' => $c, 'type' => 'shipping'];
        }
        if ($c = data_get($promosInput, 'discount.code')) {
            $promosApplied[] = [
                'code' => $c,
                'type' => data_get($promosInput, 'discount.type'),
                'percent' => data_get($promosInput, 'discount.percent'),
                'amount_cents' => (int) data_get($promosInput, 'discount.amount_cents', 0),
            ];
        }
        $meta['promos_applied'] = $promosApplied;
        $order->metadata = $meta;

        // Save changes
        $order->snapshot = $itemsSnapshot;
        $order->save();

        // ---- EMAILS on status transition ----
        $newStatus = $order->order_status;
        if ($order->email) {
            // only if status changed
            if ($oldStatus !== $newStatus) {
                try {
                    if ($newStatus === 'shipped') {
                        Mail::mailer('noreply')->to($order->email)->send(new OrderShippedMail($order));
                    } elseif ($newStatus === 'cancelled') {
                        Mail::mailer('noreply')->to($order->email)->send(new OrderCancelledMail($order));
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Order status email failed: ' . $e->getMessage());
                }
            }
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order updated successfully.');
    }




    /* --------------------------------------------------------------
     | Destroy: delete order + cascade items
     |-------------------------------------------------------------- */
    public function destroy(Order $order)
    {
        $order->items()->delete();
        $order->delete();
        return redirect()->route('admin.orders.index')->with('success', 'Order deleted.');
    }
}
