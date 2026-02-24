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
        'items.*.name' => 'nullable|string|max:255',
        'items.*.product_name' => 'nullable|string|max:255',
        'items.*.quantity' => 'required|integer|min:1',

        'items.*.product_stock_id' => 'nullable|integer',
        'items.*.color_id' => 'nullable|integer',
        'items.*.size_id' => 'nullable|integer',

        'items.*.price' => 'nullable|numeric|min:0',

        'promos' => 'array',
        'promos.shipping.code' => 'nullable|string|max:100',
        'promos.discount.code' => 'nullable|string|max:100',
        'promos.discount.type' => 'nullable|in:fixed,percentage',
        'promos.discount.percent' => 'nullable|numeric|min:0',
        'promos.discount.amount_cents' => 'nullable|integer|min:0',

        'shipping_option_id' => 'nullable|integer|exists:shipping_options,id',
        'shipping_cost' => 'nullable|numeric|min:0',
    ]);

    $currency = strtoupper(config('app.currency', 'USD'));

    // ---------- Resolve customer ----------
    $email = trim($validated['email']);
    $name  = trim((string)($validated['name'] ?? ''));

    $userId = null;
    if ($validated['customer_mode'] === 'existing') {
        $existing = User::where('email', $email)->first();
        $userId = $existing?->id ?? ($validated['user_id'] ?? null);
    } else {
        $existing = User::where('email', $email)->first();
        $userId = $existing?->id ?? null;
    }

    // ---------- Bulk load products ----------
    $productIds = collect($validated['items'])
        ->pluck('product_id')
        ->filter()
        ->map(fn($v) => (int)$v)
        ->unique()
        ->values()
        ->all();

    $products = Product::query()
        ->with([
            'images:id,product_id,image_path,thumbnail,color_code',
            'colors:id,product_id,name,color_code',
            'sizes:id,product_id,size',
            'stock:id,product_id,color_id,size_id,quantity_on_hand',
            'prices:id,product_id,color_id,size_id,price,discounted_price',
        ])
        ->whereIn('id', $productIds)
        ->get()
        ->keyBy('id');

    // priceIndex[pid]["color|size"] => row
    $priceIndex = [];
    foreach ($products as $p) {
        $idx = [];
        foreach (($p->prices ?? collect()) as $row) {
            $ck = $row->color_id ? (string)(int)$row->color_id : 'na';
            $sk = $row->size_id ? (string)(int)$row->size_id : 'na';
            $idx["{$ck}|{$sk}"] = $row;
        }
        $priceIndex[$p->id] = $idx;
    }

    // ✅ FIXED: never return 0 unless product price is truly 0
    $variantPriceFor = function (Product $p, ?int $colorId, ?int $sizeId) use ($priceIndex) {
        $ck = $colorId ? (string)$colorId : 'na';
        $sk = $sizeId ? (string)$sizeId : 'na';

        $idx = $priceIndex[$p->id] ?? [];
        $candidates = [
            "{$ck}|{$sk}",
            "{$ck}|na",
            "na|{$sk}",
            "na|na",
        ];

        $basePrice = (float)($p->price ?? 0);
        $baseDiscount = ($p->discount_price !== null && (float)$p->discount_price > 0)
            ? (float)$p->discount_price
            : null;

        foreach ($candidates as $key) {
            if (!isset($idx[$key])) continue;

            $row = $idx[$key];

            $price = (isset($row->price) && (float)$row->price > 0)
                ? (float)$row->price
                : null;

            $disc = (isset($row->discounted_price) && (float)$row->discounted_price > 0)
                ? (float)$row->discounted_price
                : null;

            // discounted wins only if it's a real discount
            if ($price !== null && $disc !== null && $disc < $price) return $disc;
            if ($price !== null) return $price;

            // if matrix price missing/0, fallback to base product values
            break;
        }

        if ($baseDiscount !== null && $baseDiscount < $basePrice) return $baseDiscount;
        return $basePrice;
    };

    // ---------- Build items + totals ----------
    $orderItemsForCreate = [];
    $itemsSnapshot = [];
    $subtotalCents = 0;

    foreach ($validated['items'] as $row) {
        $qty = max(1, (int)($row['quantity'] ?? 1));

        $productId = isset($row['product_id']) ? (int)$row['product_id'] : null;
        $productStockId = isset($row['product_stock_id']) && $row['product_stock_id'] !== '' ? (int)$row['product_stock_id'] : null;
        $colorId = isset($row['color_id']) && $row['color_id'] !== '' ? (int)$row['color_id'] : null;
        $sizeId  = isset($row['size_id']) && $row['size_id'] !== '' ? (int)$row['size_id'] : null;

        $product = $productId ? $products->get($productId) : null;

        $productName = trim((string)($row['name'] ?? $row['product_name'] ?? ''));
        $resolvedName = $productName;

        $sku = null;
        $thumbPath = null;

        $hasColors = false;
        $hasSizes  = false;
        $variant   = null;

        if ($product) {
            $hasColors = ($product->colors?->isNotEmpty() ?? false);
            $hasSizes  = ($product->sizes?->isNotEmpty() ?? false);

            if ($resolvedName === '') $resolvedName = (string)$product->name;
            $sku = $product->sku ?? null;
            $thumbPath = $product->images?->first()?->image_path;

            if ($productStockId) {
                $variant = $product->stock?->firstWhere('id', $productStockId);
                if ($variant) {
                    $colorId = $variant->color_id ? (int)$variant->color_id : null;
                    $sizeId  = $variant->size_id ? (int)$variant->size_id : null;
                } else {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["Selected variant is invalid for product #{$product->id}."],
                    ]);
                }
            }

            if (!$variant && ($hasColors || $hasSizes)) {
                if ($hasColors && !$colorId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["Color is required for “{$product->name}”."],
                    ]);
                }
                if ($hasSizes && !$sizeId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["Size is required for “{$product->name}”."],
                    ]);
                }

                $variant = $product->stock?->first(function ($r) use ($colorId, $sizeId) {
                    return ((int)($r->color_id ?? 0) === (int)($colorId ?? 0))
                        && ((int)($r->size_id  ?? 0) === (int)($sizeId  ?? 0));
                });

                if (!$variant) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["Selected options are not available for “{$product->name}”."],
                    ]);
                }

                $productStockId = (int)$variant->id;
            }

            if ($variant) {
                $available = (int)($variant->available_qty ?? $variant->quantity_on_hand ?? 0);
                if ($available <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["“{$product->name}” is out of stock."],
                    ]);
                }
                if ($qty > $available) $qty = $available;
            }
        }

        if ($resolvedName === '') $resolvedName = 'Custom Item';

        // ✅ recompute price safely (now won't go to 0 بسبب discounted_price=0)
        $unitPrice = $product
            ? $variantPriceFor($product, $colorId, $sizeId)
            : (float)($row['price'] ?? 0);

        $unitCents = (int)round($unitPrice * 100);
        $rowSubtotal = $unitCents * $qty;
        $subtotalCents += $rowSubtotal;

        $orderItemsForCreate[] = [
            'product_id' => $product?->id,
            'product_stock_id' => $productStockId,
            'color_id' => $colorId,
            'size_id' => $sizeId,

            'name' => $resolvedName,
            'sku' => $sku,
            'quantity' => $qty,

            'unit_price_cents' => $unitCents,
            'subtotal_cents' => $rowSubtotal,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => $rowSubtotal,
            'currency' => $currency,
        ];
    }

    // ---------- Shipping + tax ----------
    $shippingCents = 0;
    $taxCents = 0;
    $taxRateUsed = null;

    $selectedShip = null;
    if (!empty($validated['shipping_option_id'])) {
        $selectedShip = ShippingOption::find((int)$validated['shipping_option_id']);
    }

    if ($selectedShip) {
        $shippingCents = (int)round(((float)$selectedShip->price) * 100);

        $rate = $selectedShip->tax_percentage;
        $rate = is_null($rate) ? null : (float)$rate;
        if ($rate !== null && $rate > 1) $rate = $rate / 100;
        if ($rate === null) $rate = 0.13;

        $taxRateUsed = $rate;
        $taxCents = (int)round($subtotalCents * $rate);
    }

    $discountCents = 0;
    $totalCents = max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents);

    return \DB::transaction(function () use (
        $request,
        $validated,
        $userId,
        $name,
        $email,
        $currency,
        $subtotalCents,
        $discountCents,
        $shippingCents,
        $taxCents,
        $totalCents,
        $orderItemsForCreate,
        $selectedShip,
        $taxRateUsed
    ) {
        $order = Order::create([
            'user_id' => $userId,
            'coupon_id' => $validated['coupon_id'] ?? null,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),

            'full_name' => ($name ?: $email),
            'email' => $email,

            'currency' => $currency,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'shipping_cents' => $shippingCents,
            'tax_cents' => $taxCents,
            'total_cents' => $totalCents,

            'payment_status' => 'pending',
            'order_status' => 'processing',
            'payment_method' => $validated['payment_method'],

            'shipping_address_json' => ['raw' => $validated['shipping_address']],
            'billing_address_json' => ['raw' => $validated['billing_address'] ?? $validated['shipping_address']],

            'ip_address' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 1000),

            'metadata' => [
                'source' => 'admin',
                'shipping_option_id' => $validated['shipping_option_id'] ?? null,
                'shipping_name' => $selectedShip?->name,
                'tax_rate' => $taxRateUsed,
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
