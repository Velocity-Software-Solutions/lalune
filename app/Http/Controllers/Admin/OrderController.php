<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendReviewEmail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderItem;

use App\Models\User;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\ShippingOption;
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
        $countries = [
            'Canada' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NS' => 'Nova Scotia',
                'NT' => 'Northwest Territories',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ],

            'United States' => [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
                'DC' => 'District of Columbia',
            ],
        ];

        $shippingOptions = ShippingOption::where('status', 1)->with('cities')->get();

        $customers = User::orderBy('name')->get(['id', 'name', 'email']);
        $products = Product::with(['colors:id,product_id,name,color_code', 'sizes:id,product_id,size', 'stock:id,product_id,color_id,size_id,quantity_on_hand', 'prices:id,product_id,color_id,size_id,price,discounted_price'])->get();
        $coupons = Coupon::orderBy('code')->get(['id', 'code', 'discount_type', 'value']);
        // return $products;
        return view('admin.orders.create', compact('customers', 'products', 'coupons', 'countries', 'shippingOptions'));
    }

    /* --------------------------------------------------------------
     | Store: persist order + items
     |-------------------------------------------------------------- */

    public function store(Request $request)
    {
        // ---------- Validate ----------
        $validated = $request->validate([
            'customer_mode' => 'required|in:existing,new',

            'email' => 'required|email:rfc,dns',
            'name' => 'nullable|string|max:255',

            'user_id' => 'nullable|integer|exists:users,id',

            'shipping_address' => 'required|string|max:5000',
            'billing_address' => 'nullable|string|max:5000',

            'payment_method' => 'required|string|max:100',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'notes' => 'nullable|string|max:5000',

            'items' => 'required|array|min:1',

            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',

            // ✅ NEW (for stock matrix decrement)
            'items.*.product_stock_id' => 'nullable|integer|exists:product_stocks,id',
            'items.*.color_id' => 'nullable|integer|exists:product_colors,id',
            'items.*.size_id' => 'nullable|integer|exists:product_sizes,id',

            'promos' => 'array',
            'promos.shipping.code' => 'nullable|string|max:100',
            'promos.discount.code' => 'nullable|string|max:100',
            'promos.discount.type' => 'nullable|in:fixed,percentage',
            'promos.discount.percent' => 'nullable|numeric|min:0',
            'promos.discount.amount_cents' => 'nullable|integer|min:0',
        ]);

        $currency = strtoupper(config('app.currency', 'USD'));

        $email = trim($validated['email']);
        $name = trim((string) ($validated['name'] ?? ''));

        // Resolve/normalize customer
        $userId = null;
        if ($validated['customer_mode'] === 'existing') {
            $existing = User::where('email', $email)->first();
            $userId = $existing?->id ?? ($validated['user_id'] ?? null);
        } else {
            $existing = User::where('email', $email)->first();
            $userId = $existing?->id ?? null;
        }

        // Addresses JSON
        $shippingJson = ['raw' => $validated['shipping_address']];
        $billingJson = ['raw' => $validated['billing_address'] ?? $validated['shipping_address']];

        // Promos metadata
        $promosInput = $request->input('promos', []);
        $promosApplied = [];

        if ($code = data_get($promosInput, 'shipping.code')) {
            $promosApplied[] = ['code' => $code, 'type' => 'shipping'];
        }
        if ($code = data_get($promosInput, 'discount.code')) {
            $promosApplied[] = [
                'code' => $code,
                'type' => data_get($promosInput, 'discount.type'),
                'percent' => data_get($promosInput, 'discount.percent'),
                'amount_cents' => (int) data_get($promosInput, 'discount.amount_cents', 0),
            ];
        }

        // ---------- Persist + decrement stock safely ----------
        return \DB::transaction(function () use ($request, $validated, $userId, $name, $email, $currency, $shippingJson, $billingJson, $promosApplied) {

            $orderItemsForCreate = [];
            $itemsSnapshot = [];
            $subtotalCents = 0;

            foreach ($validated['items'] as $row) {
                $qty = (int) $row['quantity'];
                $price = (float) $row['price'];
                $cents = (int) round($price * 100);

                $productId = $row['product_id'] ?? null;

                $productStockId = (int) ($row['product_stock_id'] ?? 0);
                $colorId = (int) ($row['color_id'] ?? 0);
                $sizeId = (int) ($row['size_id'] ?? 0);

                $productName = trim((string) ($row['product_name'] ?? ''));
                $resolvedName = $productName;

                $sku = null;
                $productThumb = null;

                // ---------- Resolve product + thumb ----------
                $product = null;
                if ($productId) {
                    // lock product row so simple stock updates are safe
                    $product = Product::query()
                        ->with(['images'])
                        ->lockForUpdate()
                        ->find($productId);

                    if (!$product) {
                        throw new \Exception("Product not found.");
                    }

                    if ($resolvedName === '') {
                        $resolvedName = $product->name;
                    }

                    $sku = $product->sku ?? null;
                    $productThumb = $product->images->first()?->image_path;
                }

                if ($resolvedName === '') {
                    $resolvedName = 'Custom Item';
                }

                // ---------- Decrement inventory ----------
                // 1) If variant provided → decrement ProductStock
                $didMatrixDecrement = false;

                if ($productStockId > 0) {
                    $ps = ProductStock::query()
                        ->whereKey($productStockId)
                        ->lockForUpdate()
                        ->first();

                    if (!$ps) {
                        throw new \Exception("Variant stock not found.");
                    }

                    // Optional safety: ensure variant belongs to selected product
                    if ($productId && (int) $ps->product_id !== (int) $productId) {
                        throw new \Exception("Variant does not belong to selected product.");
                    }

                    if ((int) $ps->quantity_on_hand < $qty) {
                        throw new \Exception("Not enough stock for selected variant.");
                    }

                    $ps->decrement('quantity_on_hand', $qty);
                    $didMatrixDecrement = true;
                } elseif ($productId && ($colorId > 0 || $sizeId > 0)) {
                    // Fallback lookup by product + (color/size)
                    $q = ProductStock::query()
                        ->where('product_id', $productId)
                        ->lockForUpdate();

                    // If your schema allows NULL for "no size" or "no color", handle that:
                    if ($colorId > 0)
                        $q->where('color_id', $colorId);
                    else
                        $q->whereNull('color_id');

                    if ($sizeId > 0)
                        $q->where('size_id', $sizeId);
                    else
                        $q->whereNull('size_id');

                    $ps = $q->first();

                    if (!$ps) {
                        throw new \Exception("Variant stock row not found for selected color/size.");
                    }

                    if ((int) $ps->quantity_on_hand < $qty) {
                        throw new \Exception("Not enough stock for selected variant.");
                    }

                    $ps->decrement('quantity_on_hand', $qty);
                    $productStockId = (int) $ps->id; // keep it for order item snapshot
                    $didMatrixDecrement = true;
                }

                // 2) If no matrix decrement happened → treat as simple stock product
                if (!$didMatrixDecrement && $product) {
                    if (!is_null($product->stock_quantity)) {
                        if ((int) $product->stock_quantity < $qty) {
                            throw new \Exception("Not enough stock for {$product->name}");
                        }
                        $product->decrement('stock_quantity', $qty);
                    }
                }

                // ---------- Totals ----------
                $rowSubtotal = $cents * $qty;
                $subtotalCents += $rowSubtotal;

                // ---------- Prepare order item ----------
                $orderItemsForCreate[] = [
                    'product_id' => $productId,
                    'product_stock_id' => $productStockId ?: null,
                    'color_id' => $colorId ?: null,
                    'size_id' => $sizeId ?: null,

                    'name' => $resolvedName,
                    'sku' => $sku,
                    'quantity' => $qty,
                    'unit_price_cents' => $cents,
                    'subtotal_cents' => $rowSubtotal,
                    'discount_cents' => 0,
                    'tax_cents' => 0,
                    'total_cents' => $rowSubtotal,
                    'currency' => $currency,

                    'snapshot' => [
                        'image_url' => $productThumb ? asset('storage/' . $productThumb) : null,
                        'variant' => [
                            'product_stock_id' => $productStockId ?: null,
                            'color_id' => $colorId ?: null,
                            'size_id' => $sizeId ?: null,
                        ],
                    ],
                ];

                $itemsSnapshot[] = [
                    'description' => $resolvedName,
                    'quantity' => $qty,
                    'amount_subtotal' => $rowSubtotal,
                    'amount_total' => $rowSubtotal,
                    'currency' => $currency,

                    'product_id' => (int) ($productId ?? 0),
                    'product_stock_id' => (int) ($productStockId ?? 0),
                    'color_id' => (int) ($colorId ?? 0),
                    'size_id' => (int) ($sizeId ?? 0),

                    'image_url' => $productThumb ? asset('storage/' . $productThumb) : null,
                ];
            }

            // Totals (admin-driven)
            $discountCents = 0;
            $shippingCents = 0;
            $taxCents = 0;
            $totalCents = max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents);

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

                'snapshot' => $itemsSnapshot,
                'metadata' => [
                    'source' => 'admin',
                    'promos_applied' => $promosApplied,
                ],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($orderItemsForCreate as $li) {
                OrderItem::create($li + ['order_id' => $order->id]);
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
                    } elseif ($newStatus === 'delivered') {
                        SendReviewEmail::dispatch($order->id)
                            ->delay(now()->addDay());

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
