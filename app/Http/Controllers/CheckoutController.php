<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingOption;
use App\Services\StallionRates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf; // at the top of the file
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
        'email'            => auth()->check() ? 'nullable|email' : 'required|email',
        'full_name'        => 'required|string|max:255',
        'country'          => 'required|string',
        'city'             => 'required|string',
        'shipping_address' => 'required|string',
        'billing_address'  => 'nullable|string',
    ]);

    $cart = session('cart', []);
    if (empty($cart)) {
        return redirect()->route('checkout.index')->with('error', 'Your cart is empty.');
    }

    // ---- Normalize promos from session into ['shipping'=>..., 'discount'=>...]
    $promosRaw = session('promos', []); // could be assoc by code or a list
    $promosRaw = is_array($promosRaw) ? $promosRaw : [];
    $shippingPromo = null;
    $discountPromo = null;

    foreach ($promosRaw as $entry) {
        $type = $entry['discount_type'] ?? null;
        if ($type === 'shipping' && !$shippingPromo) {
            $shippingPromo = $entry;
        } elseif (in_array($type, ['fixed','percentage'], true) && !$discountPromo) {
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
        $qty       = (int) $item['quantity'];
        $lineCents = $unitCents * $qty;

        $items[] = [
            'name'       => (string) $item['name'],
            'unit_cents' => $unitCents,
            'qty'        => $qty,
            'line_cents' => $lineCents,
        ];
        $itemsSubtotalCents += $lineCents;
    }

    // --- Shipping
    $shippingCentsDefault = 1500; // CAD 15
    $hasFreeShipping      = !empty($promos['shipping']);
    $shippingCents        = $hasFreeShipping ? 0 : $shippingCentsDefault;

    // --- Discount (non-shipping)
    $discountPromo = $promos['discount'] ?? null;
    $discountCents = 0;
    if ($discountPromo) {
        // Prefer a precomputed amount (money) if present (from applyPromo)
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
            // skip fully discounted items from Stripe line_items
            continue;
        }

        $newUnit = $row['qty'] > 0 ? (int) floor($newLine / $row['qty']) : 0;
        $unitSum = $newUnit * $row['qty'];
        $remainder = $newLine - $unitSum;
        if ($remainder > 0 && $row['qty'] > 0) {
            $newUnit += 1; // bump by 1 cent to match line
            if (($newUnit * $row['qty']) > $newLine) {
                $newUnit = (int) floor($newLine / $row['qty']);
            }
        }
        $newUnit = max(0, $newUnit);

        $adjustedLineItems[] = [
            'price_data' => [
                'currency'     => 'cad',
                'product_data' => ['name' => $row['name']],
                'unit_amount'  => $newUnit,
            ],
            'quantity' => $row['qty'],
        ];
    }

    if (empty($adjustedLineItems)) {
        // Edge: everything discounted to zero; keep a minimal line
        $any = $items[0] ?? null;
        if ($any) {
            $adjustedLineItems[] = [
                'price_data' => [
                    'currency'     => 'cad',
                    'product_data' => ['name' => $any['name']],
                    'unit_amount'  => 1,
                ],
                'quantity' => 1,
            ];
            $shippingCents = 0;
        }
    }

    // Shipping line
    if ($shippingCents > 0) {
        $adjustedLineItems[] = [
            'price_data' => [
                'currency'     => 'cad',
                'product_data' => ['name' => 'Shipping'],
                'unit_amount'  => $shippingCents,
            ],
            'quantity' => 1,
        ];
    }

    // --- Metadata (serialize normalized promos)
    $billingMeta = [
        'bill_name'    => $request->full_name,
        'bill_email'   => $request->email,
        'bill_line1'   => $request->billing_address,
        'bill_city'    => $request->city,
        'bill_country' => $request->country,
    ];
    $shippingMeta = [
        'ship_name'    => $request->full_name,
        'ship_email'   => $request->email,
        'ship_line1'   => $request->shipping_address,
        'ship_city'    => $request->city,
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
        $promosMeta['promo_discount_code']   = $promos['discount']['code'] ?? null;
        $promosMeta['promo_discount_type']   = $promos['discount']['discount_type'] ?? null;
        $promosMeta['promo_discount_amount'] = (string) $discountCents; // cents
        if (($promos['discount']['discount_type'] ?? null) === 'percentage') {
            $promosMeta['promo_discount_percent'] = (string) ($promos['discount']['percent'] ?? $promos['discount']['value'] ?? '');
        }
    }

    $allMeta = array_filter(array_merge($baseMeta, $billingMeta, $shippingMeta, $promosMeta), fn($v) => $v !== null);

    $params = [
        'mode'        => 'payment',
        'line_items'  => $adjustedLineItems,
        'success_url' => route('checkout.confirmation') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => route('checkout.index'),
        'metadata'    => $allMeta,
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

    $stripe  = new \Stripe\StripeClient(env('STRIPE_SECRET'));
    $session = $stripe->checkout->sessions->create($params);

    return redirect()->away($session->url);
}


public function confirmation(Request $request)
{
    $sessionId = $request->query('session_id');
    abort_unless($sessionId, 404);

    $stripe  = new \Stripe\StripeClient(env('STRIPE_SECRET'));
    $session = $stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent', 'customer']]);

    if ($session->payment_status !== 'paid') {
        return redirect()->route('checkout.index')->with('error', 'Payment not completed.');
    }

    // stripe-php < 14:
    $lineItems = $stripe->checkout->sessions->allLineItems($sessionId, ['limit' => 100]);
    // stripe-php ^14: $lineItems = $stripe->checkout->sessions->listLineItems($sessionId, ['limit' => 100]);

    $piMetaArr = $session->payment_intent && $session->payment_intent->metadata
        ? $session->payment_intent->metadata->toArray() : [];
    $seMetaArr = $session->metadata ? $session->metadata->toArray() : [];

    $meta = function (string $key, $default = null) use ($piMetaArr, $seMetaArr) {
        return array_key_exists($key, $piMetaArr)
            ? $piMetaArr[$key]
            : (array_key_exists($key, $seMetaArr) ? $seMetaArr[$key] : $default);
    };

    $cust  = $session->customer_details;
    $addr  = optional($cust)->address;
    $email = optional($cust)->email ?? optional($session->customer)->email;
    $name  = optional($cust)->name;

    $bill = [
        'name'        => $meta('bill_name', $name),
        'email'       => $meta('bill_email', $email),
        'line1'       => $meta('bill_line1', null),
        'line2'       => $meta('bill_line2', null),
        'city'        => $meta('bill_city', null),
        'state'       => $meta('bill_state', null),
        'postal_code' => $meta('bill_postal', null),
        'country'     => $meta('bill_country', null),
    ];

    $ship = [
        'name'        => $meta('ship_name', $name),
        'email'       => $meta('ship_email', $email),
        'line1'       => $meta('ship_line1', optional($addr)->line1),
        'line2'       => $meta('ship_line2', optional($addr)->line2),
        'city'        => $meta('ship_city', optional($addr)->city),
        'state'       => $meta('ship_state', optional($addr)->state),
        'postal_code' => $meta('ship_postal', optional($addr)->postal_code),
        'country'     => $meta('ship_country', optional($addr)->country),
        'phone'       => optional($cust)->phone,
    ];

    $currency   = strtoupper($session->currency);
    $totalCents = (int) $session->amount_total;
    $subCents   = (int) $session->amount_subtotal;
    $discCents  = (int) (optional($session->total_details)->amount_discount ?? 0);  // Stripe-computed discounts if any
    $shipCents  = (int) (optional($session->total_details)->amount_shipping ?? 0);
    $taxCents   = (int) (optional($session->total_details)->amount_tax ?? 0);

    $paymentIntentId = is_string($session->payment_intent)
        ? $session->payment_intent
        : (optional($session->payment_intent)->id ?? null);

    // Safe user resolution
    $userId = auth()->id();
    if (!$userId) {
        $uidMeta = $meta('user_id');
        if (is_string($uidMeta) && ctype_digit($uidMeta)) {
            $candidate = (int) $uidMeta;
            if (\App\Models\User::whereKey($candidate)->exists()) {
                $userId = $candidate;
            }
        }
    }

    // Build snapshot
    $itemsSnapshot = collect($lineItems->data)->map(fn($li) => [
        'description'      => $li->description,
        'quantity'         => (int) ($li->quantity ?? 1),
        'amount_total'     => (int) ($li->amount_total ?? 0),
        'amount_subtotal'  => (int) ($li->amount_subtotal ?? 0),
        'currency'         => strtoupper($li->currency ?? $currency),
        'color'            => $li->price->product->metadata['color'] ?? null,
        'size'             => $li->price->product->metadata['size'] ?? null,
    ])->all();

    // Read promos from metadata (what we sent earlier)
    $promosApplied = [];
    $shippingCode  = $meta('promo_shipping_code');
    if ($shippingCode) {
        $promosApplied[] = ['code' => $shippingCode, 'type' => 'shipping'];
    }
    $discountCode = $meta('promo_discount_code');
    if ($discountCode) {
        $promosApplied[] = [
            'code'    => $discountCode,
            'type'    => $meta('promo_discount_type'),
            'percent' => $meta('promo_discount_percent'),
            'amount_cents' => (int) ($meta('promo_discount_amount') ?? 0),
        ];
    }

    $order = \App\Models\Order::firstOrCreate(
        ['stripe_session_id' => $sessionId],
        [
            'user_id'              => $userId,
            'coupon_id'            => null,
            'shipping_option_id'   => null,

            'order_number'         => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
            'stripe_payment_intent'=> $paymentIntentId,
            'stripe_customer_id'   => is_string($session->customer) ? $session->customer : (optional($session->customer)->id ?? null),

            'full_name'            => $ship['name'] ?? $bill['name'],
            'email'                => $ship['email'] ?? $bill['email'],
            'phone'                => $ship['phone'] ?? null,

            'currency'             => $currency,
            'subtotal_cents'       => $subCents,
            'discount_cents'       => $discCents,    // Stripe’s totals (our item price adjustments already included)
            'shipping_cents'       => $shipCents,
            'tax_cents'            => $taxCents,
            'total_cents'          => $totalCents,

            'payment_status'       => 'paid',
            'order_status'         => 'processing',
            'payment_method'       => 'stripe_checkout',
            'paid_at'              => now(),

            'shipping_address_json'=> $ship,
            'billing_address_json' => $bill,

            'coupon_code'          => null,
            'ip_address'           => $request->ip(),
            'user_agent'           => substr((string) $request->userAgent(), 0, 1000),

            'snapshot'             => $itemsSnapshot,
            'metadata'             => [
                'stripe'         => ['mode' => $session->mode],
                'promos_applied' => $promosApplied, // <-- persist promos used
            ],
            'notes' => null,
        ]
    );

    if ($order->wasRecentlyCreated) {
        foreach ($lineItems->data as $li) {
            $qty       = (int) ($li->quantity ?? 1);
            $lineTotal = (int) ($li->amount_total ?? 0);
            $lineSub   = (int) ($li->amount_subtotal ?? $lineTotal);
            $unitCents = $qty > 0 ? intdiv($lineSub, $qty) : 0;

            \App\Models\OrderItem::create([
                'order_id'          => $order->id,
                'product_id'        => null,
                'name'              => $li->description,
                'quantity'          => $qty,
                'unit_price_cents'  => $unitCents,
                'subtotal_cents'    => $lineSub,
                'discount_cents'    => max(0, $lineSub - $lineTotal),
                'tax_cents'         => 0,
                'total_cents'       => $lineTotal,
                'currency'          => strtoupper($li->currency ?? $currency),
                'snapshot'          => ['stripe_line_item' => $li],
            ]);
        }

        // (Optional) Mark promo usage if you have a PromoCode model
        if (!empty($promosApplied) && class_exists(\App\Models\PromoCode::class)) {
            foreach ($promosApplied as $applied) {
                try {
                    $pc = \App\Models\PromoCode::where('code', $applied['code'])->first();
                    if ($pc) {
                        // Safely increment used_count
                        $pc->increment('used_count');
                        // If you have an order_promo pivot, attach it here with amount:
                        // $order->promoCodes()->attach($pc->id, ['amount_cents' => $applied['amount_cents'] ?? 0]);
                    }
                } catch (\Throwable $e) {
                    // Do not block checkout on bookkeeping errors
                    \Log::warning('Promo usage increment failed: '.$e->getMessage());
                }
            }
        }

        // Clear cart & promos
        session()->forget(['cart', 'coupon', 'promos']);
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
