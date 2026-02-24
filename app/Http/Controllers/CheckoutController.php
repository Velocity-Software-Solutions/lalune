<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductStock;
use App\Models\PromoCode;
use App\Models\ShippingOption;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf; // at the top of the file
use Log;
use Stripe\StripeClient;
use User;


class CheckoutController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        $coupon = session('coupon');

        $countries = [
            // 'US' => [
            //     'AL' => 'Alabama',
            //     'AK' => 'Alaska',
            //     'AZ' => 'Arizona',
            //     'AR' => 'Arkansas',
            //     'CA' => 'California',
            //     'CO' => 'Colorado',
            //     'CT' => 'Connecticut',
            //     'DE' => 'Delaware',
            //     'FL' => 'Florida',
            //     'GA' => 'Georgia',
            //     'HI' => 'Hawaii',
            //     'ID' => 'Idaho',
            //     'IL' => 'Illinois',
            //     'IN' => 'Indiana',
            //     'IA' => 'Iowa',
            //     'KS' => 'Kansas',
            //     'KY' => 'Kentucky',
            //     'LA' => 'Louisiana',
            //     'ME' => 'Maine',
            //     'MD' => 'Maryland',
            //     'MA' => 'Massachusetts',
            //     'MI' => 'Michigan',
            //     'MN' => 'Minnesota',
            //     'MS' => 'Mississippi',
            //     'MO' => 'Missouri',
            //     'MT' => 'Montana',
            //     'NE' => 'Nebraska',
            //     'NV' => 'Nevada',
            //     'NH' => 'New Hampshire',
            //     'NJ' => 'New Jersey',
            //     'NM' => 'New Mexico',
            //     'NY' => 'New York',
            //     'NC' => 'North Carolina',
            //     'ND' => 'North Dakota',
            //     'OH' => 'Ohio',
            //     'OK' => 'Oklahoma',
            //     'OR' => 'Oregon',
            //     'PA' => 'Pennsylvania',
            //     'RI' => 'Rhode Island',
            //     'SC' => 'South Carolina',
            //     'SD' => 'South Dakota',
            //     'TN' => 'Tennessee',
            //     'TX' => 'Texas',
            //     'UT' => 'Utah',
            //     'VT' => 'Vermont',
            //     'VA' => 'Virginia',
            //     'WA' => 'Washington',
            //     'WV' => 'West Virginia',
            //     'WI' => 'Wisconsin',
            //     'WY' => 'Wyoming',
            // ],
            'Canada' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NT' => 'Northwest Territories',
                'NS' => 'Nova Scotia',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ],
        ];



        $shippingOptions = ShippingOption::where('status', 1)->with('cities')->get();

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
        'phone' => 'required|string|max:25|regex:/^\+?[0-9\s\-]{7,20}$/',
        'country' => 'required|string',
        'city' => 'required|string|max:255',
        'state' => 'required|string|max:255',        // province/state code (e.g. ON)
        'state_name' => 'nullable|string|max:255',   // province name (e.g. Ontario)
        'shipping_address' => 'required|string',
        'billing_address' => 'nullable|string',

        'shipping_option_id' => 'nullable|integer|exists:shipping_options,id',
    ]);

    // 1) Load cart
    $cart = session('cart', []);
    if (empty($cart) || !is_array($cart)) {
        return redirect()->route('checkout.index')->with('error', 'Your cart is empty.');
    }

    // 2) Load products + relations for stock & pricing validation
    $productIds = collect($cart)
        ->pluck('product_id')
        ->map(fn($v) => (int) $v)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $with = [
        'stock:id,product_id,color_id,size_id,quantity_on_hand',
        'colors:id,product_id,color_code',
        'sizes:id,product_id,size',
        'prices:id,product_id,color_id,size_id,price,discounted_price',
    ];

    $products = Product::with($with)
        ->whereIn('id', $productIds)
        ->get()
        ->keyBy('id');

    // Build fast price index: $priceIndex[pid]["color|size"] => row
    $priceIndex = [];
    foreach ($products as $p) {
        $idx = [];
        foreach (($p->prices ?? collect()) as $row) {
            $ck = $row->color_id ? (string) (int) $row->color_id : 'na';
            $sk = $row->size_id ? (string) (int) $row->size_id : 'na';
            $idx["{$ck}|{$sk}"] = $row;
        }
        $priceIndex[$p->id] = $idx;
    }

    $variantPriceFor = function (Product $p, ?int $colorId, ?int $sizeId) use ($priceIndex) {
        $ck = $colorId ? (string) $colorId : 'na';
        $sk = $sizeId ? (string) $sizeId : 'na';

        $idx = $priceIndex[$p->id] ?? [];
        $candidates = [
            "{$ck}|{$sk}",
            "{$ck}|na",
            "na|{$sk}",
            "na|na",
        ];

        foreach ($candidates as $key) {
            if (!isset($idx[$key])) continue;
            $row = $idx[$key];
            $val = $row->discounted_price ?? $row->price ?? null;
            if ($val !== null && $val !== '') {
                return (float) $val;
            }
        }

        if ($p->discount_price !== null && (float) $p->discount_price > 0) {
            return (float) $p->discount_price;
        }
        return (float) $p->price;
    };

    // 3) Validate cart lines (stock + variant ids) and sync price
    $changes = [];
    $modified = false;

    foreach ($cart as $key => &$line) {
        $pid = (int) ($line['product_id'] ?? 0);
        $qty = max(1, (int) ($line['quantity'] ?? 1));
        $p = $products->get($pid);

        if (!$p || (int) $p->status !== 1) {
            unset($cart[$key]);
            $modified = true;
            $changes[] = "Removed “" . (($line['name'] ?? 'item') ?: 'item') . "” (no longer available).";
            continue;
        }

        // Resolve variant (stock) + ids
        $variant = null;

        if (!empty($line['product_stock_id'])) {
            $variant = $p->stock->firstWhere('id', (int) $line['product_stock_id']);
            if ($variant) {
                $line['color_id'] = (int) ($variant->color_id ?? 0);
                $line['size_id']  = (int) ($variant->size_id ?? 0);
            }
        }

        if (!$variant) {
            $colorId = (int) ($line['color_id'] ?? 0);
            $sizeId  = (int) ($line['size_id'] ?? 0);

            if (!$colorId && !empty($line['color'])) {
                $hex = strtoupper((string) $line['color']);
                $colorId = (int) optional($p->colors->firstWhere('color_code', $hex))->id;
            }
            if (!$sizeId && !empty($line['size'])) {
                $sz = (string) $line['size'];
                $sizeId = (int) optional($p->sizes->firstWhere('size', $sz))->id;
            }

            if ($colorId && empty($line['color_id'])) {
                $line['color_id'] = $colorId;
                $modified = true;
            }
            if ($sizeId && empty($line['size_id'])) {
                $line['size_id'] = $sizeId;
                $modified = true;
            }

            if ($colorId || $sizeId) {
                $variant = $p->stock->first(fn($row) =>
                    (int) $row->color_id === (int) $colorId &&
                    (int) $row->size_id  === (int) $sizeId
                );
            }

            if ($variant && empty($line['product_stock_id'])) {
                $line['product_stock_id'] = (int) $variant->id;
                $modified = true;
            }
        }

        $available = $variant ? (int) $variant->quantity_on_hand : (int) $p->stock_quantity;

        if ($available <= 0) {
            unset($cart[$key]);
            $modified = true;
            $changes[] = "Removed “" . (($line['name'] ?? 'item') ?: 'item') . "” (out of stock).";
            continue;
        }

        if ($qty > $available) {
            $line['quantity'] = $available;
            $modified = true;
            $changes[] = "Updated “" . (($line['name'] ?? 'item') ?: 'item') . "” to {$available} (limited stock).";
        }

        $colorId = (int) ($line['color_id'] ?? 0);
        $sizeId  = (int) ($line['size_id'] ?? 0);

        $variantPrice = $variantPriceFor($p, $colorId ?: null, $sizeId ?: null);

        if (!isset($line['price']) || (float) $line['price'] !== (float) $variantPrice) {
            $line['price'] = (float) $variantPrice;
            $modified = true;
            $changes[] = "Updated price for “" . (($line['name'] ?? 'item') ?: 'item') . "”.";
        }

        if (empty($line['image_url'])) {
            $line['image_url'] = $p->main_image_url ?? $p->image_url ?? $p->thumbnail_url ?? null;
        }
    }
    unset($line);

    if ($modified) {
        $cart = array_values(array_filter($cart, fn($row) => isset($row['quantity']) && (int) $row['quantity'] > 0));
        if (empty($cart)) {
            session()->forget('cart');
            return redirect()->route('cart.index')->with('error', 'All items in your cart became unavailable.');
        }
        session()->put('cart', $cart);
        return redirect()->route('cart.index')->with('warning', implode(' ', $changes) ?: 'We updated your cart based on current stock and prices.');
    }

    // Reserve (15 minutes)
    $sessionKey = $request->session()->getId();
    $userId = auth()->id();
    $result = InventoryService::reserveCart($cart, $sessionKey, $userId, 15);

    if (!empty($result['cartModified'])) {
        $newCart = $result['cart'] ?? [];
        if (empty($newCart)) {
            session()->forget('cart');
            return redirect()->route('cart.index')->with('error', 'All items became unavailable.');
        }
        session()->put('cart', $newCart);
        return redirect()->route('cart.index')->with('warning', implode(' ', (array) ($result['changes'] ?? [])));
    }

    $cart = $result['cart'] ?? $cart;
    session()->put('cart', $cart);

    // ---- Normalize promos
    $promosRaw = session('promos', []);
    $promosRaw = is_array($promosRaw) ? $promosRaw : [];
    $shippingPromo = collect($promosRaw)->first(fn($e) => ($e['discount_type'] ?? null) === 'shipping');
    $discountPromo = collect($promosRaw)->first(fn($e) => in_array(($e['discount_type'] ?? null), ['fixed', 'percentage'], true));
    $promos = ['shipping' => $shippingPromo, 'discount' => $discountPromo];

    // ---- Build items
    $items = [];
    $itemsSubtotalCents = 0;

    foreach ($cart as $item) {
        $unitCents = (int) round(((float) ($item['price'] ?? 0)) * 100);
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $lineCents = $unitCents * $qty;

        $items[] = [
            'name' => (string) ($item['name'] ?? 'Item'),
            'unit_cents' => $unitCents,
            'qty' => $qty,
            'line_cents' => $lineCents,
            'meta' => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'product_stock_id' => (int) ($item['product_stock_id'] ?? 0),
                'color_id' => (int) ($item['color_id'] ?? 0),
                'size_id' => (int) ($item['size_id'] ?? 0),
                'image_url' => (string) ($item['image_url'] ?? ''),
            ],
        ];

        $itemsSubtotalCents += $lineCents;
    }

    // ---- Discount (keep your logic)
    $discountCents = 0;
    if (!empty($promos['discount'])) {
        $dp = $promos['discount'];
        $type = $dp['discount_type'] ?? null;

        if (isset($dp['amount'])) {
            $discountCents = max(0, (int) round(((float) $dp['amount']) * 100));
        } elseif ($type === 'percentage') {
            $pct = max(0, min(100, (float) ($dp['percent'] ?? $dp['value'] ?? 0)));
            $discountCents = (int) round(($pct / 100) * $itemsSubtotalCents);
        } elseif ($type === 'fixed') {
            $discountCents = (int) round(((float) ($dp['value'] ?? 0)) * 100);
        }

        $discountCents = min($discountCents, $itemsSubtotalCents);
    }

    // Stripe line_items (discount pro-rata)
    $adjustedLineItems = [];
    $remainingDiscount = $discountCents;
    $lastIndex = array_key_last($items);

    foreach ($items as $idx => $row) {
        $share = 0;
        if ($discountCents > 0 && $itemsSubtotalCents > 0) {
            $share = (int) floor(($row['line_cents'] * $discountCents) / $itemsSubtotalCents);
            if ($idx === $lastIndex) {
                $share = min($row['line_cents'], $remainingDiscount);
            }
            $remainingDiscount -= $share;
        }

        $newLine = max(0, $row['line_cents'] - $share);
        if ($newLine === 0) continue;

        $newUnit = $row['qty'] > 0 ? (int) intdiv($newLine, $row['qty']) : 0;
        $newUnit = max(0, $newUnit);

        $adjustedLineItems[] = [
            'price_data' => [
                'currency' => 'cad',
                'product_data' => [
                    'name' => $row['name'],
                    'metadata' => array_filter([
                        'product_id' => (string) ($row['meta']['product_id'] ?? ''),
                        'product_stock_id' => (string) ($row['meta']['product_stock_id'] ?? ''),
                        'color_id' => (string) ($row['meta']['color_id'] ?? ''),
                        'size_id' => (string) ($row['meta']['size_id'] ?? ''),
                    ]),
                ],
                'unit_amount' => $newUnit,
            ],
            'quantity' => $row['qty'],
        ];
    }

    // ---- Shipping option (your DB)
    $country = strtoupper(trim((string) $request->input('country', 'CA')));
    $stateCode = strtoupper(trim((string) $request->input('state', '')));

    $requestedShippingId = $request->filled('shipping_option_id')
        ? (int) $request->input('shipping_option_id')
        : null;

    $selectedOption = null;
    if ($requestedShippingId) {
        $selectedOption = ShippingOption::query()
            ->where('id', $requestedShippingId)
            ->where('status', 1)
            ->where('country', $country)
            ->with(['cities:id,shipping_option_id,city'])
            ->first();
    }

    $shippingName = $selectedOption?->name ?: 'Shipping';
    $shippingCentsDefault = 1500;

    $shippingCents = !empty($promos['shipping'])
        ? 0
        : (int) round(((float) ($selectedOption?->price ?? ($shippingCentsDefault / 100))) * 100);

    // ---- Prefill addresses via Stripe Customer (NOT via Checkout params)
    $shippingLine1 = trim((string) $request->input('shipping_address', ''));
    $billingLine1  = trim((string) ($request->input('billing_address') ?: $shippingLine1));

    $countryRaw = trim((string) $request->input('country', ''));

// Map common labels to ISO2 for Stripe
$countryMap = [
    'CANADA' => 'CA',
    'CA' => 'CA',
    'UNITED STATES' => 'US',
    'USA' => 'US',
    'US' => 'US',
];

$countryIso = $countryMap[strtoupper($countryRaw)] ?? strtoupper($countryRaw);

// Safety: if still not 2 letters, default
if (strlen($countryIso) !== 2) {
    $countryIso = 'CA';
}

    $shippingAddressForStripe = array_filter([
        'line1'   => $shippingLine1 ?: null,
        'city'    => trim((string) $request->input('city', '')) ?: null,
        'state'   => $stateCode ?: null,
        'country' => $countryIso ?: null,
        // 'postal_code' => trim((string) $request->input('postal_code', '')) ?: null,
    ], fn($v) => $v !== null && $v !== '');

    $billingAddressForStripe = array_filter([
        'line1'   => $billingLine1 ?: null,
        'city'    => trim((string) $request->input('city', '')) ?: null,
        'state'   => $stateCode ?: null,
        'country' => $country ?: null,
    ], fn($v) => $v !== null && $v !== '');

    // ---- Metadata + cart snapshot (keep yours)
    $reservationIdsCsv = implode(',', (array) ($result['reservation_ids'] ?? []));
    $cartSnapshot = collect($cart)->map(function ($it) {
        return [
            'product_id' => (int) ($it['product_id'] ?? 0),
            'product_stock_id' => (int) ($it['product_stock_id'] ?? 0),
            'name' => (string) ($it['name'] ?? ''),
            'qty' => (int) ($it['quantity'] ?? 1),
            'price' => (float) ($it['price'] ?? 0),
            'image_url' => $it['image_path'] ?? ($it['image_url'] ?? null),
            'color' => $it['color'] ?? null,
            'size' => $it['size'] ?? null,
            'color_id' => (int) ($it['color_id'] ?? 0),
            'size_id' => (int) ($it['size_id'] ?? 0),
        ];
    })->values()->all();

    $allMeta = array_filter([
        'user_id' => auth()->check() ? (string) auth()->id() : null,

        'bill_name' => $request->full_name,
        'bill_email' => $request->email,
        'bill_phone' => $request->phone,
        'bill_line1' => $request->billing_address,
        'bill_city' => $request->city,
        'bill_state' => $request->state,
        'bill_country' => $request->country,

        'ship_name' => $request->full_name,
        'ship_email' => $request->email,
        'ship_phone' => $request->phone,
        'ship_line1' => $request->shipping_address,
        'ship_city' => $request->city,
        'ship_state' => $request->state,
        'ship_country' => $request->country,

        'shipping_option_id' => $selectedOption?->id ? (string) $selectedOption->id : null,
        'shipping_option_name' => $shippingName,
        'shipping_amount_cents' => (string) $shippingCents,

        'promo_shipping_code' => $promos['shipping']['code'] ?? null,
        'promo_discount_code' => $promos['discount']['code'] ?? null,
        'promo_discount_type' => $promos['discount']['discount_type'] ?? null,
        'promo_discount_percent' => (string) ($promos['discount']['percent'] ?? $promos['discount']['value'] ?? ''),
        'promo_discount_amount_cents' => (string) $discountCents,

        'reservation_ids' => $reservationIdsCsv,
        'reservation_expires_at' => optional($result['expires_at'] ?? null)?->toIso8601String(),
        'cart_snapshot' => json_encode($cartSnapshot),
    ], fn($v) => $v !== null && $v !== '');

    // ---- Stripe Checkout
    $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

    // Create/update a customer so Stripe Checkout is prefilled
    $customerId = null;

    if (auth()->check() && !empty(auth()->user()->stripe_id)) {
        $customerId = auth()->user()->stripe_id;

        $stripe->customers->update($customerId, [
            'name' => (string) $request->input('full_name'),
            'phone' => (string) $request->input('phone'),
            'address' => $billingAddressForStripe ?: null,
            'shipping' => [
                'name' => (string) $request->input('full_name'),
                'phone' => (string) $request->input('phone'),
                'address' => $shippingAddressForStripe,
            ],
        ]);
    } else {
        // If you want guests ALSO to be prefilled, create the customer here:
        $customer = $stripe->customers->create([
            'email' => $request->filled('email') ? $request->input('email') : null,
            'name' => (string) $request->input('full_name'),
            'phone' => (string) $request->input('phone'),
            'address' => $billingAddressForStripe ?: null,
            'shipping' => [
                'name' => (string) $request->input('full_name'),
                'phone' => (string) $request->input('phone'),
                'address' => $shippingAddressForStripe,
            ],
            'metadata' => [
                'source' => 'checkout_prefill',
            ],
        ]);

        $customerId = $customer->id;
    }

    $params = [
        'mode' => 'payment',
        'line_items' => $adjustedLineItems,

        // Stripe calculates tax
        'automatic_tax' => ['enabled' => true],

        // Stripe collects/validates address for tax (will be prefilled from customer)
        'shipping_address_collection' => [
            'allowed_countries' => [$countryIso ?: 'CA'],
        ],
        'billing_address_collection' => 'required',

        // optional: Stripe can collect phone (prefills from customer usually)
        'phone_number_collection' => ['enabled' => true],

        // Charge shipping via Stripe
        'shipping_options' => [
            [
                'shipping_rate_data' => [
                    'type' => 'fixed_amount',
                    'fixed_amount' => [
                        'amount' => $shippingCents,
                        'currency' => 'cad',
                    ],
                    'display_name' => $shippingName,
                ],
            ],
        ],

        // Attach the customer we updated/created
        'customer' => $customerId,

        // Save any changes the user makes in Checkout back to the customer
        'customer_update' => [
            'address' => 'auto',
            'shipping' => 'auto',
            'name' => 'auto',
        ],

        'metadata' => $allMeta,
        'payment_intent_data' => ['metadata' => $allMeta],

        'success_url' => route('checkout.confirmation') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => route('checkout.index'),
    ];

    $session = $stripe->checkout->sessions->create($params);

    return redirect()->away($session->url);
}
    public function confirmation(Request $request)
    {
        $sessionId = $request->query('session_id');
        abort_unless($sessionId, 404);

        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $session = $stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent', 'customer']]);

        if (($session->payment_status ?? null) !== 'paid') {
            return redirect()->route('checkout.index')->with('error', 'Payment not completed.');
        }

        // Read line items (no need to expand price.product metadata anymore)
        $lineItems = $stripe->checkout->sessions->allLineItems($sessionId, ['limit' => 100]);

        // Merge metadata from PI and Session (PI wins)
        $piMeta = ($session->payment_intent && $session->payment_intent->metadata) ? $session->payment_intent->metadata->toArray() : [];
        $seMeta = $session->metadata ? $session->metadata->toArray() : [];

        $meta = function (string $key, $default = null) use ($piMeta, $seMeta) {
            return array_key_exists($key, $piMeta) ? $piMeta[$key] : (array_key_exists($key, $seMeta) ? $seMeta[$key] : $default);
        };

        // --- Resolve user safely
        $userId = auth()->id();
        if (!$userId) {
            $uidMeta = $meta('user_id');
            if (is_string($uidMeta) && ctype_digit($uidMeta) && User::whereKey((int) $uidMeta)->exists()) {
                $userId = (int) $uidMeta;
            }
        }

        // --- Commit reservations ONCE (fixes your double-commit bug)
        $reservationIds = array_filter(array_map('intval', explode(',', (string) $meta('reservation_ids', ''))));
        $committed = false;

        try {
            if (!empty($reservationIds)) {
                $activeCount = \App\Models\StockReservation::query()
                    ->whereIn('id', $reservationIds)
                    ->where('status', true)
                    ->where('expires_at', '>', now())
                    ->count();

                if ($activeCount > 0) {
                    InventoryService::commitReservations($reservationIds);
                    $committed = true;
                    Log::info('Reservations committed', ['count' => $activeCount, 'ids' => $reservationIds]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('commitReservations failed: ' . $e->getMessage());
            $committed = false;
        }

        // Fallback decrement if nothing to commit
        if (!$committed) {
            \DB::transaction(function () use ($meta, $lineItems) {
                // cart_snapshot is our source of truth for product ids/stock ids
                $snap = json_decode((string) $meta('cart_snapshot', '[]'), true) ?: [];
                $byName = collect($snap)->keyBy(fn($r) => (string) ($r['name'] ?? ''));

                foreach ($lineItems->data as $li) {
                    $qty = (int) ($li->quantity ?? 1);
                    if ($qty <= 0)
                        continue;

                    // try match by description/name
                    $row = $byName->get((string) ($li->description ?? '')) ?? null;

                    $productStockId = (int) ($row['product_stock_id'] ?? 0);
                    $productId = (int) ($row['product_id'] ?? 0);

                    if ($productStockId) {
                        $ps = ProductStock::query()->whereKey($productStockId)->lockForUpdate()->first();
                        if ($ps) {
                            $ps->quantity_on_hand = max(0, ((int) $ps->quantity_on_hand) - $qty);
                            $ps->save();
                        }
                    } elseif ($productId) {
                        $p = Product::query()->whereKey($productId)->lockForUpdate()->first();
                        if ($p && isset($p->stock_quantity)) {
                            $p->stock_quantity = max(0, ((int) $p->stock_quantity) - $qty);
                            $p->save();
                        }
                    }
                }
            });

            Log::info('Fallback decrement completed');
        }

        // Release any leftover holds for this browsing session
        try {
            InventoryService::releaseBySession($request->session()->getId());
        } catch (\Throwable $e) {
            Log::warning('Releasing holds by session failed: ' . $e->getMessage());
        }

        // Build addresses from metadata + Stripe customer_details fallback
        $cust = $session->customer_details;
        $addr = optional($cust)->address;
        $email = optional($cust)->email ?? optional($session->customer)->email;
        $name = optional($cust)->name;

        $bill = [
            'name' => $meta('bill_name', $name),
            'email' => $meta('bill_email', $email),
            'line1' => $meta('bill_line1'),
            'line2' => $meta('bill_line2'),
            'city' => $meta('bill_city'),
            'state' => $meta('bill_state'),
            'postal_code' => $meta('bill_postal'),
            'country' => $meta('bill_country'),
        ];

        $ship = [
            'name' => $meta('ship_name', $name),
            'email' => $meta('ship_email', $email),
            'line1' => $meta('ship_line1', optional($addr)->line1),
            'line2' => $meta('ship_line2', optional($addr)->line2),
            'city' => $meta('ship_city', optional($addr)->city),
            'state' => $meta('ship_state', optional($addr)->state),
            'postal_code' => $meta('ship_postal', optional($addr)->postal_code),
            'country' => $meta('ship_country', optional($addr)->country),
            'phone' => $meta('ship_phone'),
        ];

        $currency = strtoupper($session->currency);
        $totalCents = (int) $session->amount_total;
        $subCents = (int) $session->amount_subtotal;
        $discCents = (int) (optional($session->total_details)->amount_discount ?? 0);
        $shipCents = (int) (optional($session->total_details)->amount_shipping ?? 0);
        $taxCents = (int) (optional($session->total_details)->amount_tax ?? 0);

        if ($taxCents === 0) {
            $taxCents = (int) ($meta('tax_amount_cents') ?? 0);
        }

        $paymentIntentId = is_string($session->payment_intent)
            ? $session->payment_intent
            : (optional($session->payment_intent)->id ?? null);

        // Build snapshot from cart_snapshot (fixes missing images + variant data)
        $cartSnapshot = json_decode((string) $meta('cart_snapshot', '[]'), true) ?: [];

        $itemsSnapshot = collect($lineItems->data)->map(function ($li) use ($currency, $cartSnapshot) {
            $match = collect($cartSnapshot)->first(fn($r) => (string) ($r['name'] ?? '') === (string) ($li->description ?? ''));

            return [
                'description' => (string) ($li->description ?? ''),
                'quantity' => (int) ($li->quantity ?? 1),
                'amount_total' => (int) ($li->amount_total ?? 0),
                'amount_subtotal' => (int) ($li->amount_subtotal ?? 0),
                'currency' => strtoupper($li->currency ?? $currency),

                'product_id' => (int) ($match['product_id'] ?? 0),
                'product_stock_id' => (int) ($match['product_stock_id'] ?? 0),
                'color' => $match['color'] ?? null,
                'size' => $match['size'] ?? null,
                'color_id' => (int) ($match['color_id'] ?? 0),
                'size_id' => (int) ($match['size_id'] ?? 0),
                'image_url' => $match['image_url'] ?? null,

                'variant' => [
                    'color' => $match['color'] ?? null,
                    'size' => $match['size'] ?? null,
                    'color_id' => (int) ($match['color_id'] ?? 0),
                    'size_id' => (int) ($match['size_id'] ?? 0),
                ],
            ];
        })->all();

        // Promos for receipt (from metadata)
        $promosApplied = [];
        if ($code = $meta('promo_shipping_code')) {
            $promosApplied[] = ['code' => $code, 'type' => 'shipping'];
        }
        if ($code = $meta('promo_discount_code')) {
            $promosApplied[] = [
                'code' => $code,
                'type' => $meta('promo_discount_type'),
                'percent' => $meta('promo_discount_percent'),
                'amount_cents' => (int) ($meta('promo_discount_amount') ?? 0),
            ];
        }

        // Idempotent order creation
        $order = Order::firstOrCreate(
            ['stripe_session_id' => $sessionId],
            [
                'user_id' => $userId,
                'coupon_id' => null,
                'shipping_option_id' => null,

                'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'stripe_payment_intent' => $paymentIntentId,
                'stripe_customer_id' => is_string($session->customer) ? $session->customer : (optional($session->customer)->id ?? null),

                'full_name' => $ship['name'] ?? $bill['name'],
                'email' => $ship['email'] ?? $bill['email'],
                'phone' => $ship['phone'] ?? null,

                'currency' => $currency,
                'subtotal_cents' => $subCents,
                'discount_cents' => $discCents,
                'shipping_cents' => $shipCents,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,

                'payment_status' => 'paid',
                'order_status' => 'processing',
                'payment_method' => 'stripe_checkout',
                'paid_at' => now(),

                'shipping_address_json' => $ship,
                'billing_address_json' => $bill,

                'coupon_code' => null,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),

                'snapshot' => $itemsSnapshot,
                'metadata' => [
                    'stripe' => ['mode' => $session->mode],
                    'promos_applied' => $promosApplied,
                    'cart_snapshot' => $cartSnapshot,
                ],
                'notes' => null,
            ]
        );

        // Create OrderItems only once
        if ($order->wasRecentlyCreated) {
            // Prefetch color/size labels in bulk (optimization)
            $colorIds = collect($cartSnapshot)->pluck('color_id')->filter()->unique()->values()->all();
            $sizeIds = collect($cartSnapshot)->pluck('size_id')->filter()->unique()->values()->all();

            $colors = ProductColor::whereIn('id', $colorIds)->get(['id', 'name', 'color_code'])->keyBy('id');
            $sizes = ProductSize::whereIn('id', $sizeIds)->get(['id', 'size'])->keyBy('id');

            foreach ($lineItems->data as $li) {
                $qty = (int) ($li->quantity ?? 1);
                if ($qty <= 0)
                    continue;

                $lineTotal = (int) ($li->amount_total ?? 0);
                $lineSub = (int) ($li->amount_subtotal ?? $lineTotal);
                $unitCents = $qty > 0 ? intdiv($lineSub, $qty) : 0;

                $match = collect($cartSnapshot)->first(fn($r) => (string) ($r['name'] ?? '') === (string) ($li->description ?? ''));

                $productId = (int) ($match['product_id'] ?? 0) ?: null;
                $productStockId = (int) ($match['product_stock_id'] ?? 0) ?: null;
                $colorId = (int) ($match['color_id'] ?? 0) ?: null;
                $sizeId = (int) ($match['size_id'] ?? 0) ?: null;

                $colorModel = $colorId ? ($colors[$colorId] ?? null) : null;
                $sizeModel = $sizeId ? ($sizes[$sizeId] ?? null) : null;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'product_stock_id' => $productStockId,
                    'color_id' => $colorId,
                    'size_id' => $sizeId,

                    'name' => (string) ($li->description ?? 'Item'),
                    'quantity' => $qty,
                    'unit_price_cents' => $unitCents,
                    'subtotal_cents' => $lineSub,
                    'discount_cents' => max(0, $lineSub - $lineTotal),
                    'tax_cents' => 0,
                    'total_cents' => $lineTotal,
                    'currency' => strtoupper($li->currency ?? $currency),

                    'snapshot' => [
                        'product_id' => $productId,
                        'product_stock_id' => $productStockId,
                        'color_id' => $colorId,
                        'size_id' => $sizeId,

                        'image_url' => $match['image_url'] ?? null,
                        'color_name' => $colorModel?->name,
                        'color_code' => $colorModel?->color_code,
                        'size' => $sizeModel?->size,

                        'variant' => [
                            'color_id' => $colorId,
                            'size_id' => $sizeId,
                            'color_name' => $colorModel?->name,
                            'color_code' => $colorModel?->color_code,
                            'size' => $sizeModel?->size,
                        ],
                    ],
                ]);
            }

            // Promo bookkeeping
            if (!empty($promosApplied) && class_exists(PromoCode::class)) {
                foreach ($promosApplied as $applied) {
                    try {
                        if ($pc = PromoCode::where('code', $applied['code'])->first()) {
                            $pc->increment('used_count');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Promo usage increment failed: ' . $e->getMessage());
                    }
                }
            }

            session()->forget(['cart', 'coupon', 'promos']);
        }

        // Only email once
        if (!$order->getAttribute('receipt_emailed_at')) {
            try {
                Mail::mailer('noreply')
                    ->to($order->email)
                    ->bcc(['info@lalunebyne.com', 'velocitysoftwaresolutions000@gmail.com'])
                    ->send(new \App\Mail\OrderConfirmationMail($order));

                $order->forceFill(['receipt_emailed_at' => now()])->save();
            } catch (\Throwable $e) {
                Log::warning('Order receipt email failed: ' . $e->getMessage());
            }
        }

        return view('checkout.receipt', compact('order', 'session'));
    }



    // public function rate(StallionRates $stallion, Request $request)
    // {
    //     $rates = $stallion->quote(
    //         [
    //             'city' => 'London',
    //             'province_code' => 'ON',
    //             'postal_code' => 'N6P 0A8',
    //             'country_code' => 'CA',
    //             'is_residential' => true,
    //         ],
    //         [
    //             'weight_unit' => 'lbs',
    //             'weight' => 0.6,
    //             'length' => 9,
    //             'width' => 12,
    //             'height' => 1,
    //             'size_unit' => 'cm',
    //         ],
    //         [
    //             'value' => 20,                   // 👈 REQUIRED when no items[]
    //             'currency' => 'CAD',
    //             'package_contents' => 'Merchandise',
    //             // 'postage_types' => ['Cheapest Tracked'],
    //         ]
    //     );


    //     return response()->json($rates);
    // }
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
