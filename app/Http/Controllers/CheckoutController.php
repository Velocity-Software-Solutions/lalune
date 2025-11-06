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
            'state' => 'required|string|max:255',
            'shipping_address' => 'required|string',
            'billing_address' => 'nullable|string',
        ]);

        // 1) Load cart
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('checkout.index')->with('error', 'Your cart is empty.');
        }

        // 2) Re-validate inventory & price against DB (no writes here, just verify)
        $productIds = array_values(array_unique(array_map(fn($row) => (int) ($row['product_id'] ?? 0), $cart)));
        $products = Product::with(['stock', 'colors', 'sizes'])
            ->whereIn('id', $productIds)->get()->keyBy('id');

        $changes = [];    // human messages
        $modified = false;

        foreach ($cart as $key => &$line) {
            $pid = (int) ($line['product_id'] ?? 0);
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $p = $products->get($pid);

            if (!$p || (int) $p->status !== 1) {
                unset($cart[$key]);
                $modified = true;
                $changes[] = "Removed “{$line['name']}” (no longer available).";
                continue;
            }

            // Current canonical unit price
            $currentUnit = $p->discount_price !== null && $p->discount_price >= 0
                ? (float) $p->discount_price
                : (float) $p->price;

            // Map to variant if any
            $hasColors = $p->colors->isNotEmpty();
            $hasSizes = $p->sizes->isNotEmpty();

            $selectedHex = isset($line['color']) && $line['color'] ? strtoupper((string) $line['color']) : null;
            $selectedSize = isset($line['size']) && $line['size'] ? (string) $line['size'] : null;

            $variant = null;

            // Prefer product_stock_id if you saved it in the cart
            if (!empty($line['product_stock_id'])) {
                $variant = $p->stock->firstWhere('id', (int) $line['product_stock_id']);
            } elseif ($hasColors || $hasSizes) {
                // Map hex & size name to IDs
                $colorId = null;
                if ($hasColors && $selectedHex) {
                    $color = $p->colors->firstWhere('color_code', $selectedHex);
                    $colorId = $color?->id;
                }
                $sizeId = null;
                if ($hasSizes && $selectedSize) {
                    $size = $p->sizes->firstWhere('size', $selectedSize);
                    $sizeId = $size?->id;
                }

                if ($hasColors || $hasSizes) {
                    $variant = $p->stock->first(function ($row) use ($colorId, $sizeId) {
                        return (int) $row->color_id === (int) $colorId
                            && (int) $row->size_id === (int) $sizeId;
                    });
                }
            }

            // Determine available units
            $available = $variant ? (int) $variant->quantity_on_hand
                : (int) $p->stock_quantity;

            if ($available <= 0) {
                unset($cart[$key]);
                $modified = true;
                $changes[] = "Removed “{$line['name']}” (out of stock).";
                continue;
            }

            // Clamp requested qty to available
            if ($qty > $available) {
                $line['quantity'] = $available;
                $modified = true;
                $changes[] = "Updated “{$line['name']}” to {$available} (limited stock).";
            }

            // Update unit price if changed
            if (!isset($line['price']) || (float) $line['price'] !== $currentUnit) {
                $line['price'] = $currentUnit;
                $modified = true;
                $changes[] = "Updated price for “{$line['name']}”.";
            }

            // Sync variant id into cart for downstream checks
            if ($variant && empty($line['product_stock_id'])) {
                $line['product_stock_id'] = $variant->id;
                $modified = true;
            }
        }
        unset($line); // break reference

        // If we changed the cart, save and send user back to review
        if ($modified) {
            // Remove any now-empty lines just in case
            $cart = array_filter($cart, fn($row) => isset($row['quantity']) && (int) $row['quantity'] > 0);
            if (empty($cart)) {
                session()->forget('cart');
                return redirect()->route('cart.index')->with('error', 'All items in your cart became unavailable.');
            }
            session()->put('cart', $cart);
            // Show a single consolidated warning
            $msg = implode(' ', $changes);
            return redirect()->route('cart.index')->with('warning', $msg ?: 'We updated your cart based on current stock and prices.');
        }

        $sessionKey = $request->session()->getId();
        $userId = auth()->id();

        // Reserve (15 minutes)
        $result = InventoryService::reserveCart(session('cart', []), $sessionKey, $userId, 15);

        // If cart changed (clamped/removed lines), save + send back to review
        if ($result['cartModified']) {
            if (empty($result['cart'])) {
                session()->forget('cart');
                return redirect()->route('cart.index')->with('error', 'All items became unavailable.');
            }
            session()->put('cart', $result['cart']);
            return redirect()->route('cart.index')->with('warning', implode(' ', $result['changes']));
        }

        // Save cart back just in case
        session()->put('cart', $result['cart']);

        // … continue building Stripe line_items as you already do …

        // Add reservation IDs to metadata so the webhook can commit them
        $reservationIdsCsv = implode(',', $result['reservation_ids']);
        $params['metadata']['reservation_ids'] = $reservationIdsCsv;
        $params['payment_intent_data']['metadata']['reservation_ids'] = $reservationIdsCsv;

        // (Optional, show expiry to user)
        $params['metadata']['reservation_expires_at'] = $result['expires_at']->toIso8601String();
        $params['payment_intent_data']['metadata']['reservation_expires_at'] = $result['expires_at']->toIso8601String();

        // 3) From here, your original totals/promo/Stripe logic — unchanged, but using the (possibly) updated $cart
        // ---- Normalize promos from session into ['shipping'=>..., 'discount'=>...]
        $promosRaw = session('promos', []);
        $promosRaw = is_array($promosRaw) ? $promosRaw : [];
        $shippingPromo = null;
        $discountPromo = null;

        foreach ($promosRaw as $entry) {
            $type = $entry['discount_type'] ?? null;
            if ($type === 'shipping' && !$shippingPromo) {
                $shippingPromo = $entry;
            } elseif (in_array($type, ['fixed', 'percentage'], true) && !$discountPromo) {
                $discountPromo = $entry;
            }
        }
        $promos = [
            'shipping' => $shippingPromo,
            'discount' => $discountPromo,
        ];

        // --- Build item subtotals (in cents)
        $items = [];
        $itemsSubtotalCents = 0;

        foreach ($cart as $item) {
            $unitCents = (int) round(((float) $item['price']) * 100);
            $qty = (int) $item['quantity'];
            $lineCents = $unitCents * $qty;

            $items[] = [
                'name' => (string) $item['name'],
                'unit_cents' => $unitCents,
                'qty' => $qty,
                'line_cents' => $lineCents,
                'meta' => [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'product_stock_id' => (int) ($item['product_stock_id'] ?? 0),
                    'color_id' => (int) ($item['color_id'] ?? 0),
                    'size_id' => (int) ($item['size_id'] ?? 0),
                ],
            ];

            $itemsSubtotalCents += $lineCents;
        }

        // --- Shipping
        $shippingCentsDefault = 1500; // CAD 15
        $hasFreeShipping = !empty($promos['shipping']);
        $shippingCents = $hasFreeShipping ? 0 : $shippingCentsDefault;

        // --- Discount
        $discountPromo = $promos['discount'] ?? null;
        $discountCents = 0;
        if ($discountPromo) {
            if (isset($discountPromo['amount'])) {
                $discountCents = max(0, (int) round(((float) $discountPromo['amount']) * 100));
            } elseif (($discountPromo['discount_type'] ?? null) === 'percentage') {
                $pct = max(0, min(100, (float) ($discountPromo['percent'] ?? $discountPromo['value'] ?? 0)));
                $discountCents = (int) round(($pct / 100) * $itemsSubtotalCents);
            } elseif (($discountPromo['discount_type'] ?? null) === 'fixed') {
                $discountCents = (int) round(((float) ($discountPromo['value'] ?? 0)) * 100);
            }
            $discountCents = min($discountCents, $itemsSubtotalCents);
        }


        // --- Tax (13%) on items AFTER discount (exclude shipping)
        $taxRate = 0.13;

        // Items subtotal after discount in cents
        $itemsAfterDiscountCents = max(0, $itemsSubtotalCents - $discountCents);

        // Compute tax
        $taxCents = (int) round($itemsAfterDiscountCents * $taxRate);


        // --- Distribute discount across items (pro-rata)
        $adjustedLineItems = [];
        $remainingDiscount = $discountCents;

        foreach ($items as $idx => $row) {
            $share = 0;
            if ($discountCents > 0 && $itemsSubtotalCents > 0) {
                $share = (int) floor(($row['line_cents'] * $discountCents) / $itemsSubtotalCents);
                if ($idx === array_key_last($items)) {
                    $share = min($row['line_cents'], $remainingDiscount);
                }
                $remainingDiscount -= $share;
            }

            $newLine = max(0, $row['line_cents'] - $share);
            if ($newLine === 0) {
                continue; // skip fully discounted items
            }

            $newUnit = $row['qty'] > 0 ? (int) floor($newLine / $row['qty']) : 0;
            $unitSum = $newUnit * $row['qty'];
            $remainder = $newLine - $unitSum;
            if ($remainder > 0 && $row['qty'] > 0) {
                $newUnit += 1;
                if (($newUnit * $row['qty']) > $newLine) {
                    $newUnit = (int) floor($newLine / $row['qty']);
                }
            }
            $newUnit = max(0, $newUnit);

            // Optionally include per-line metadata so you can reconcile items on webhook
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

        if (empty($adjustedLineItems)) {
            $any = $items[0] ?? null;
            if ($any) {
                $adjustedLineItems[] = [
                    'price_data' => [
                        'currency' => 'cad',
                        'product_data' => ['name' => $any['name']],
                        'unit_amount' => 1,
                    ],
                    'quantity' => 1,
                ];
                $shippingCents = 0;
            }
        }
        if ($taxCents > 0) {
            $adjustedLineItems[] = [
                'price_data' => [
                    'currency' => 'cad',
                    'product_data' => ['name' => 'Tax (13%)'],
                    'unit_amount' => $taxCents,
                ],
                'quantity' => 1,
            ];
        }


        if ($shippingCents > 0) {
            $adjustedLineItems[] = [
                'price_data' => [
                    'currency' => 'cad',
                    'product_data' => ['name' => 'Shipping'],
                    'unit_amount' => $shippingCents,
                ],
                'quantity' => 1,
            ];
        }

        // --- Metadata (serialize normalized promos)
        $billingMeta = [
            'bill_name' => $request->full_name,
            'bill_email' => $request->email,
            'bill_phone' => $request->phone,
            'bill_line1' => $request->billing_address,
            'bill_city' => $request->city,
            'bill_state' => $request->state,
            'bill_country' => $request->country,
        ];
        $shippingMeta = [
            'ship_name' => $request->full_name,
            'ship_email' => $request->email,
            'ship_phone' => $request->phone,
            'ship_line1' => $request->shipping_address,
            'ship_city' => $request->city,
            'ship_state' => $request->state,
            'ship_country' => $request->country,
        ];
        $baseMeta = [];
        if (auth()->check()) {
            $baseMeta['user_id'] = (string) auth()->id();
        }

        $promosMeta = [];
        if (!empty($promos['shipping'])) {
            $promosMeta['promo_shipping_code'] = $promos['shipping']['code'] ?? null;
        }
        if (!empty($promos['discount'])) {
            $promosMeta['promo_discount_code'] = $promos['discount']['code'] ?? null;
            $promosMeta['promo_discount_type'] = $promos['discount']['discount_type'] ?? null;
            $promosMeta['promo_discount_amount'] = (string) ($discountCents ?? 0);
            if (($promos['discount']['discount_type'] ?? null) === 'percentage') {
                $promosMeta['promo_discount_percent'] = (string) ($promos['discount']['percent'] ?? $promos['discount']['value'] ?? '');
            }
        }

        $allMeta = array_filter(array_merge($baseMeta, $billingMeta, $shippingMeta, $promosMeta), fn($v) => $v !== null);
        $allMeta['tax_rate_percent'] = '13';
        $allMeta['tax_amount_cents'] = (string) $taxCents;

        $params = [
            'mode' => 'payment',
            'line_items' => $adjustedLineItems,
            'success_url' => route('checkout.confirmation') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.index'),
            'metadata' => $allMeta,
            'payment_intent_data' => ['metadata' => $allMeta],
        ];

        if (auth()->check() && !empty(auth()->user()->stripe_id)) {
            $params['customer'] = auth()->user()->stripe_id;
        } else {
            $params['customer_creation'] = 'always';
            if ($request->filled('email')) {
                $params['customer_email'] = $request->input('email');
            }
        }

        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $session = $stripe->checkout->sessions->create($params);

        return redirect()->away($session->url);
    }


    public function confirmation(Request $request)
    {
        $sessionId = $request->query('session_id');
        abort_unless($sessionId, 404);

        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $session = $stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent', 'customer']]);

        if ($session->payment_status !== 'paid') {
            return redirect()->route('checkout.index')->with('error', 'Payment not completed.');
        }

        // Expand price.product so we can read product metadata (color/size/image_url)
        // stripe-php < 14:
        $lineItems = $stripe->checkout->sessions->allLineItems($sessionId, [
            'limit' => 100,
            'expand' => ['data.price.product'],
        ]);

        // stripe-php ^14 would be:
        // $lineItems = $stripe->checkout->sessions->listLineItems($sessionId, ['limit' => 100, 'expand' => ['data.price.product']]);

        // Merge metadata from PI and Session
        $piMetaArr = $session->payment_intent && $session->payment_intent->metadata
            ? $session->payment_intent->metadata->toArray() : [];
        $seMetaArr = $session->metadata ? $session->metadata->toArray() : [];

        $meta = function (string $key, $default = null) use ($piMetaArr, $seMetaArr) {
            return array_key_exists($key, $piMetaArr)
                ? $piMetaArr[$key]
                : (array_key_exists($key, $seMetaArr) ? $seMetaArr[$key] : $default);
        };

        // --- Commit reservations if they still exist; else fallback decrement from paid line items
        $committed = false;

        try {
            $reservationIdsCsv = (string) ($meta('reservation_ids') ?? '');
            $reservationIds = array_filter(array_map('intval', explode(',', $reservationIdsCsv)));

            if (!empty($reservationIds)) {
                // Check if there are still active, non-expired holds for these IDs
                $activeCount = \App\Models\StockReservation::query()
                    ->whereIn('id', $reservationIds)
                    ->where('status', true)
                    ->where('expires_at', '>', now())
                    ->count();

                if ($activeCount > 0) {
                    // Normal path: commit (this decrements product_stocks / products)
                    InventoryService::commitReservations($reservationIds);
                    $committed = true;
                    \Log::info('Reservations committed', ['count' => $activeCount, 'ids' => $reservationIds]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('commitReservations failed, will try fallback decrement: ' . $e->getMessage());
            $committed = false;
        }

        if (!$committed) {
            // Graceful fallback: decrement from the *paid* Stripe line items (idempotent-ish)
            \DB::transaction(function () use ($lineItems) {
                foreach ($lineItems->data as $li) {
                    $qty = (int) ($li->quantity ?? 1);
                    if ($qty <= 0)
                        continue;

                    // Read IDs from product metadata on the Stripe line item
                    $pm = (isset($li->price->product->metadata) && $li->price->product->metadata)
                        ? $li->price->product->metadata->toArray()
                        : [];

                    $productStockId = (int) ($pm['product_stock_id'] ?? 0);
                    $productId = (int) ($pm['product_id'] ?? 0);

                    if ($productStockId) {
                        // Variant-based decrement
                        $ps = ProductStock::query()->whereKey($productStockId)->lockForUpdate()->first();
                        if ($ps) {
                            // Prevent negative; if you prefer hard-fail on insufficient, add a guard/throw here
                            $ps->quantity_on_hand = max(0, ((int) $ps->quantity_on_hand) - $qty);
                            $ps->save();
                        } else {
                            Log::warning('Fallback decrement: product_stock not found', ['product_stock_id' => $productStockId, 'qty' => $qty]);
                        }
                    } elseif ($productId) {
                        // Product-level decrement (no variants)
                        $p = Product::query()->whereKey($productId)->lockForUpdate()->first();
                        if ($p && isset($p->stock_quantity)) {
                            $p->stock_quantity = max(0, ((int) $p->stock_quantity) - $qty);
                            $p->save();
                        } else {
                            Log::warning('Fallback decrement: product not found or no stock_quantity field', ['product_id' => $productId, 'qty' => $qty]);
                        }
                    } else {
                        Log::warning('Fallback decrement skipped: no product_stock_id/product_id in Stripe metadata for line item', [
                            'description' => (string) ($li->description ?? ''),
                        ]);
                    }
                }
            });

            \Log::info('Fallback decrement completed (no active reservations to commit)');
        }


        // ---- Commit reservations & release leftovers for this browsing session
        try {
            $reservationIdsCsv = (string) ($meta('reservation_ids') ?? '');
            $reservationIds = array_filter(array_map('intval', array_filter(explode(',', $reservationIdsCsv))));
            if (!empty($reservationIds)) {
                InventoryService::commitReservations($reservationIds);
            }
        } catch (\Throwable $e) {
            Log::error('Reservation commit failed on confirmation for session ' . $sessionId . ': ' . $e->getMessage());
        }

        try {
            InventoryService::releaseBySession($request->session()->getId());
        } catch (\Throwable $e) {
            Log::warning('Releasing holds by session failed: ' . $e->getMessage());
        }

        $cust = $session->customer_details;
        $addr = optional($cust)->address;
        $email = optional($cust)->email ?? optional($session->customer)->email;
        $name = optional($cust)->name;

        $bill = [
            'name' => $meta('bill_name', $name),
            'email' => $meta('bill_email', $email),
            'line1' => $meta('bill_line1', null),
            'line2' => $meta('bill_line2', null),
            'city' => $meta('bill_city', null),
            'state' => $meta('bill_state', null),
            'postal_code' => $meta('bill_postal', null),
            'country' => $meta('bill_country', null),
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
            'phone' => $meta('ship_phone', optional($addr)->phone),
        ];

        $currency = strtoupper($session->currency);
        $totalCents = (int) $session->amount_total;
        $subCents = (int) $session->amount_subtotal;
        $discCents = (int) (optional($session->total_details)->amount_discount ?? 0);
        $shipCents = (int) (optional($session->total_details)->amount_shipping ?? 0);
        // Prefer Automatic Tax (if ever enabled)
        $taxCents = (int) (optional($session->total_details)->amount_tax ?? 0);

        // Fallback to our own metadata (manual tax line)
        if ($taxCents === 0) {
            $taxCents = (int) ($meta('tax_amount_cents') ?? 0);
        }

        // Final fallback: sum any line items named "Tax ..."
        if ($taxCents === 0) {
            $taxCents = collect($lineItems->data)
                ->filter(function ($li) {
                    $desc = (string) ($li->description ?? '');
                    return stripos($desc, 'tax') === 0; // starts with "Tax"
                })
                ->sum(function ($li) {
                    return (int) ($li->amount_total ?? 0);
                });
        }

        $paymentIntentId = is_string($session->payment_intent)
            ? $session->payment_intent
            : (optional($session->payment_intent)->id ?? null);

        // Safe user resolution
        $userId = auth()->id();
        if (!$userId) {
            $uidMeta = $meta('user_id');
            if (is_string($uidMeta) && ctype_digit($uidMeta)) {
                $candidate = (int) $uidMeta;
                if (User::whereKey($candidate)->exists()) {
                    $userId = $candidate;
                }
            }
        }

        // ---- Build an order-level snapshot of the line items including variant info
        $itemsSnapshot = collect($lineItems->data)->map(function ($li) use ($currency) {
            // Pull product metadata safely
            $prodMeta = [];
            if (isset($li->price, $li->price->product) && is_object($li->price->product) && isset($li->price->product->metadata)) {
                $prodMeta = $li->price->product->metadata->toArray();
            }

            // Prefer flat keys, then nested variant.* (matches your later read pattern)
            $color = $prodMeta['color'] ?? $prodMeta['color_code'] ?? data_get($prodMeta, 'variant.color');
            $size = $prodMeta['size'] ?? data_get($prodMeta, 'variant.size');

            $imageUrl = $prodMeta['image_url'] ?? null;
            $colorName = $prodMeta['color_name'] ?? ($prodMeta['colorLabel'] ?? null);

            return [
                'description' => $li->description,
                'quantity' => (int) ($li->quantity ?? 1),
                'amount_total' => (int) ($li->amount_total ?? 0),
                'amount_subtotal' => (int) ($li->amount_subtotal ?? 0),
                'currency' => strtoupper($li->currency ?? $currency),

                // Variant info at root
                'color' => $color,
                'size' => $size,
                'color_name' => $colorName,
                'image_url' => $imageUrl,

                // And under a nested object too (so: $snap['color'] ?? data_get($snap, 'variant.color'))
                'variant' => [
                    'color' => $color,
                    'size' => $size,
                ],
            ];
        })->all();

        // ---- Promos (from our metadata)
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
                ],
                'notes' => null,
            ]
        );

        if ($order->wasRecentlyCreated) {
            foreach ($lineItems->data as $li) {
                $qty = (int) ($li->quantity ?? 1);
                $lineTotal = (int) ($li->amount_total ?? 0);
                $lineSub = (int) ($li->amount_subtotal ?? $lineTotal);
                $unitCents = $qty > 0 ? intdiv($lineSub, $qty) : 0;

                $pm = (isset($li->price->product->metadata) && $li->price->product->metadata)
                    ? $li->price->product->metadata->toArray()
                    : [];

                $productId = (int) ($pm['product_id'] ?? 0) ?: null;
                $productStockId = (int) ($pm['product_stock_id'] ?? 0) ?: null;
                $colorId = (int) ($pm['color_id'] ?? 0) ?: null;
                $sizeId = (int) ($pm['size_id'] ?? 0) ?: null;

                $colorModel = $colorId ? ProductColor::find($colorId) : null;
                $sizeModel = $sizeId ? ProductSize::find($sizeId) : null;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId, // if you want to persist it
                    'name' => $li->description,
                    'quantity' => $qty,
                    'unit_price_cents' => $unitCents,
                    'subtotal_cents' => $lineSub,
                    'discount_cents' => max(0, $lineSub - $lineTotal),
                    'tax_cents' => 0,
                    'total_cents' => $lineTotal,
                    'currency' => strtoupper($li->currency ?? $currency),

                    'snapshot' => [
                        'stripe_line_item' => $li,

                        // IDs
                        'product_id' => $productId,
                        'product_stock_id' => $productStockId,
                        'color_id' => $colorId,
                        'size_id' => $sizeId,

                        // Friendly labels (useful in views)
                        'color_name' => $colorModel?->name,
                        'color_hex' => $colorModel?->color_code,
                        'size_label' => $sizeModel?->size,

                        // Nested variant too
                        'variant' => [
                            'color_id' => $colorId,
                            'size_id' => $sizeId,
                            'color_name' => $colorModel?->name,
                            'size' => $sizeModel?->size,
                            'color_hex' => $colorModel?->color_code,
                        ],
                    ],
                ]);
            }


            // (Optional) promo bookkeeping
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

            // clear session cart/promos
            session()->forget(['cart', 'coupon', 'promos']);
        }

        // Only email once
        if (!$order->getAttribute('receipt_emailed_at')) {
            try {
                Mail::mailer('noreply')->to($order->email)->bcc(['info@lalunebyne.com','velocitysoftwaresolutions000@gmail.com'])->send(new OrderConfirmationMail($order));
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
