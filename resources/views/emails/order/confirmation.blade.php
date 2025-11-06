@php
    // ===== Amount helpers =====
    $currency = $order->currency ?? 'USD';
    $currencySymbols = ['USD'=>'$','EUR'=>'€','GBP'=>'£','AED'=>'AED','SAR'=>'SAR','EGP'=>'EGP'];
    $currSym = $currencySymbols[$currency] ?? $currency;

    $fmt   = fn ($cents) => number_format(max(0, (int) ($cents ?? 0)) / 100, 2);
    $money = fn ($cents) => $currSym . ' ' . $fmt($cents);

    // ===== Meta / promos =====
    $meta = (array) ($order->metadata ?? []);
    $promosApplied = (array) ($meta['promos_applied'] ?? []);

    // ===== Line helpers =====
    $isShip = fn ($li) => strcasecmp(trim((string) ($li->name ?? '')), 'Shipping') === 0;
    $isTax  = fn ($li) => stripos(trim((string) ($li->name ?? '')), 'Tax') === 0;

    $allItems  = collect($order->items ?? []);
    $itemLines = $allItems->reject(fn ($li) => $isShip($li) || $isTax($li)); // <-- PRODUCTS ONLY

    // ===== Display amounts (items only) =====
    $displayItemsSubtotalCents = (int) $itemLines->sum('subtotal_cents');
    $displayItemsDiscountCents = (int) $itemLines->sum('discount_cents');

    // ===== Shipping: prefer order column, fallback to line item =====
    $displayShippingCents   = (int) ($order->shipping_cents ?? 0);
    $shippingFromLinesCents = (int) $allItems->filter($isShip)->sum('total_cents');
    if ($displayShippingCents === 0 && $shippingFromLinesCents > 0) {
        $displayShippingCents = $shippingFromLinesCents;
    }

    // ===== Tax: order column -> metadata -> Tax line items =====
    $displayTaxCents = (int) ($order->tax_cents ?? 0);
    if ($displayTaxCents === 0) {
        $displayTaxCents = (int) ($meta['tax_amount_cents'] ?? 0);
    }
    if ($displayTaxCents === 0) {
        $displayTaxCents = (int) $allItems->filter($isTax)->sum('total_cents');
    }

    // Optional blocks
    $billing  = $order->billing_address ?? null;
    $shipping = $order->shipping_address ?? null;
    $payMeth  = $order->payment_method ?? null;

    $preheader = "Order #{$order->order_number} • " . $order->created_at->format('M j, Y g:i A');
@endphp
<!doctype html>
<html lang="en" style="margin:0;padding:0;">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->order_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .preheader{display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;}
        .btn:hover{filter:brightness(.95)}
        @media screen and (max-width:640px){
            .container{width:100%!important;border-radius:0!important}
            .px-24{padding-left:16px!important;padding-right:16px!important}
            .py-20{padding-top:16px!important;padding-bottom:16px!important}
            .stack-sm{display:block!important;width:100%!important}
            .text-right-sm{text-align:left!important}
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#111;line-height:1.4;">
<div class="preheader">{{ $preheader }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:28px 0;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" class="container" style="width:600px;max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04);">

    <!-- Header -->
    <tr>
        <td class="px-24 py-20" style="padding:22px 24px;border-bottom:1px solid #eee;">
            <table width="100%" role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="vertical-align:middle;">
                        <div style="font-weight:700;font-size:18px;color:#111;">Lalune By NE</div>
                        <div style="font-size:12px;color:#7a7a7a;margin-top:2px;">Order confirmation</div>
                    </td>
                    <td align="right" class="text-right-sm" style="vertical-align:middle;">
                        <span style="display:inline-block;background:#eef6ff;color:#1a73e8;border:1px solid #d6e8ff;padding:6px 10px;border-radius:999px;font-size:12px;">
                            Order #{{ $order->order_number }}
                        </span>
                    </td>
                </tr>
            </table>
            <h1 style="margin:14px 0 0;font-size:20px;line-height:1.2;">Thanks for your order! 🎉</h1>
            <p style="margin:6px 0 0;font-size:13px;color:#555;">{{ $order->created_at->format('M j, Y g:i A') }}</p>
        </td>
    </tr>

    <!-- Total ribbon -->
    <tr>
        <td style="padding:0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0a7e55;color:#fff;">
                <tr>
                    <td class="px-24" style="padding:12px 24px;font-size:13px;">
                        <strong>Total Paid:</strong>
                        <span style="font-weight:700;margin-left:8px;">{{ $money($order->total_cents) }}</span>
                    </td>
                    <td align="right" class="px-24 text-right-sm" style="padding:12px 24px;font-size:12px;opacity:.95;">
                        {{ strtoupper($currency) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- PRODUCTS (no shipping/tax lines) -->
    <tr>
        <td class="px-24" style="padding:18px 24px;">
            <h3 style="margin:0 0 10px;font-size:15px;">Products</h3>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
                <thead>
                <tr>
                    <th align="left"  style="font-size:12px;color:#666;padding:10px 0;border-bottom:1px solid #eee;">Product</th>
                    <th align="right" style="font-size:12px;color:#666;padding:10px 0;border-bottom:1px solid #eee;">Qty</th>
                    <th align="right" style="font-size:12px;color:#666;padding:10px 0;border-bottom:1px solid #eee;">Unit</th>
                    <th align="right" style="font-size:12px;color:#666;padding:10px 0;border-bottom:1px solid #eee;">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($itemLines as $it) {{-- <--- only items --}}
                    @php
                        $unit = (int) ($it->unit_price_cents ?? 0);
                        $qty  = (int) ($it->quantity ?? 1);
                        $sub  = (int) ($it->subtotal_cents ?? $unit * $qty);
                        $disc = (int) ($it->discount_cents ?? 0);
                        $tot  = (int) ($it->total_cents ?? max(0, $sub - $disc));
                    @endphp
                    <tr>
                        <td style="padding:10px 0;font-size:13px;color:#111;">
                            <div style="font-weight:600;">{{ $it->name ?? 'Item' }}</div>
                            @if (!empty($it->sku))
                                <div style="font-size:11px;color:#888;margin-top:2px;">SKU: {{ $it->sku }}</div>
                            @endif
                        </td>
                        <td align="right" style="padding:10px 0;font-size:13px;color:#111;">{{ $qty }}</td>
                        <td align="right" style="padding:10px 0;font-size:13px;color:#111;">{{ $money($unit) }}</td>
                        <td align="right" style="padding:10px 0;font-size:13px;color:#111;">
                            @if ($disc > 0)
                                <div><s style="color:#999;">{{ $money($sub) }}</s></div>
                                <div style="color:#0a7e55;font-weight:bold;">{{ $money($tot) }}</div>
                                <div style="color:#0a7e55;font-size:11px;">− {{ $money($disc) }}</div>
                            @else
                                {{ $money($sub) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </td>
    </tr>

    <!-- CHARGES (Shipping & Tax shown separately, not mixed with products) -->
    <tr>
        <td class="px-24" style="padding:4px 24px 2px;">
            <h3 style="margin:0 0 10px;font-size:15px;">Charges</h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <!-- Shipping card -->
                    <td class="stack-sm" valign="top" style="width:50%;padding:6px 6px 6px 0;">
                        <table role="presentation" width="100%" style="border:1px solid #e6e6e6;border-radius:10px;">
                            <tr>
                                <td style="padding:12px 14px;">
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">Shipping</div>
                                    @if ($displayShippingCents > 0)
                                        <div style="font-size:14px;font-weight:700;color:#111;">{{ $money($displayShippingCents) }}</div>
                                    @else
                                        <div style="font-size:14px;font-weight:700;color:#0a7e55;">Free</div>
                                    @endif
                                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Delivery & handling</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <!-- Tax card -->
                    <td class="stack-sm" valign="top" style="width:50%;padding:6px 0 6px 6px;">
                        <table role="presentation" width="100%" style="border:1px solid #e6e6e6;border-radius:10px;">
                            <tr>
                                <td style="padding:12px 14px;">
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">Tax</div>
                                    <div style="font-size:14px;font-weight:700;color:#111;">{{ $money($displayTaxCents) }}</div>
                                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">VAT/GST as applicable</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Promotions -->
    <tr>
        <td class="px-24" style="padding:8px 24px 6px;">
            <h3 style="margin:0 0 10px;font-size:15px;">Promotions</h3>
            @if (empty($promosApplied))
                <p style="margin:0 0 10px;font-size:13px;color:#666;">No promotions applied.</p>
            @else
                <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="padding:0;">
                @foreach ($promosApplied as $p)
                    @php
                        $type    = $p['type'] ?? '';
                        $code    = strtoupper($p['code'] ?? '');
                        $amountC = (int) ($p['amount_cents'] ?? 0);
                        $percent = $p['percent'] ?? null;
                        $label = match ($type) {
                            'shipping'   => 'Free Shipping',
                            'percentage' => ($percent ? (float)$percent . '% Off' : 'Percentage Off'),
                            'fixed'      => 'Amount Off',
                            default      => 'Promotion'
                        };
                    @endphp
                    <span style="display:inline-block;margin:0 8px 8px 0;padding:6px 10px;border-radius:999px;background:#f1f5f9;border:1px solid #e5e9ef;font-size:12px;color:#0f172a;">
                        <strong style="font-weight:600;">{{ $label }}</strong> — <span style="font-family:monospace">{{ $code }}</span>
                        @if ($amountC > 0) <span style="color:#0a7e55;"> (− {{ $money($amountC) }})</span> @endif
                    </span>
                @endforeach
                </td></tr></table>
            @endif
        </td>
    </tr>

    <!-- Totals -->
    <tr>
        <td class="px-24" style="padding:4px 24px 18px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
                <tr>
                    <td align="right" style="font-size:13px;color:#666;padding:6px 0;">Items Subtotal</td>
                    <td align="right" style="font-size:13px;color:#111;padding:6px 0;">{{ $money($displayItemsSubtotalCents) }}</td>
                </tr>
                <tr>
                    <td align="right" style="font-size:13px;color:#666;padding:6px 0;">Discount</td>
                    <td align="right" style="font-size:13px;color:#0a7e55;padding:6px 0;">− {{ $money($displayItemsDiscountCents) }}</td>
                </tr>
                <tr>
                    <td align="right" style="font-size:13px;color:#666;padding:6px 0;">Shipping</td>
                    <td align="right" style="font-size:13px;color:#111;padding:6px 0;">{{ $money($displayShippingCents) }}</td>
                </tr>
                <tr>
                    <td align="right" style="font-size:13px;color:#666;padding:6px 0;">Tax</td>
                    <td align="right" style="font-size:13px;color:#111;padding:6px 0;">{{ $money($displayTaxCents) }}</td>
                </tr>
                <tr><td colspan="2" style="padding-top:8px;"><div style="border-top:1px solid #eee;"></div></td></tr>
                <tr>
                    <td align="right" style="font-size:14px;color:#111;padding-top:10px;font-weight:bold;">Total</td>
                    <td align="right" style="font-size:16px;color:#111;padding-top:10px;font-weight:800;">{{ $money($order->total_cents) }}</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Optional blocks & footer kept as-is from your version -->
    @if ($billing || $shipping || $payMeth)
    <tr>
        <td class="px-24" style="padding:4px 24px 18px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    @if ($shipping)
                        <td class="stack-sm" valign="top" style="width:50%;padding:8px 0;">
                            <div style="font-weight:700;font-size:13px;margin-bottom:6px;">Shipping Address</div>
                            <div style="font-size:13px;color:#111;">
                                {{ $shipping->name ?? '' }}<br>
                                {{ $shipping->line1 ?? '' }}<br>
                                @if(!empty($shipping->line2)) {{ $shipping->line2 }}<br>@endif
                                {{ $shipping->city ?? '' }} {{ $shipping->state ?? '' }} {{ $shipping->postal_code ?? '' }}<br>
                                {{ $shipping->country ?? '' }}
                            </div>
                        </td>
                    @endif
                    @if ($billing)
                        <td class="stack-sm" valign="top" style="width:50%;padding:8px 0;">
                            <div style="font-weight:700;font-size:13px;margin-bottom:6px;">Billing Address</div>
                            <div style="font-size:13px;color:#111;">
                                {{ $billing->name ?? '' }}<br>
                                {{ $billing->line1 ?? '' }}<br>
                                @if(!empty($billing->line2)) {{ $billing->line2 }}<br>@endif
                                {{ $billing->city ?? '' }} {{ $billing->state ?? '' }} {{ $billing->postal_code ?? '' }}<br>
                                {{ $billing->country ?? '' }}
                            </div>
                        </td>
                    @endif
                </tr>
                @if ($payMeth)
                <tr>
                    <td colspan="2" style="padding-top:10px;">
                        <div style="font-weight:700;font-size:13px;margin-bottom:6px;">Payment Method</div>
                        <div style="font-size:13px;color:#111;">
                            {{ $payMeth['brand'] ?? 'Card' }}
                            @if (!empty($payMeth['last4'])) •••• {{ $payMeth['last4'] }} @endif
                            @if (!empty($payMeth['exp_month']) && !empty($payMeth['exp_year'])) (Exp {{ $payMeth['exp_month'] }}/{{ $payMeth['exp_year'] }}) @endif
                        </div>
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
    @endif

    <tr>
        <td class="px-24" style="padding:18px 24px;border-top:1px solid #eee;">
            <table role="presentation" width="100%">
                <tr>
                    <td align="right" class="text-right-sm" style="font-size:12px;color:#666;">
                        Questions? Reply to this email and we’ll help.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr><td class="px-24" style="padding:14px 24px;font-size:11px;color:#8a8a8a;">© {{ now()->year }} Lalune By NE, All rights reserved.</td></tr>
</table>
</td>
</tr>
</table>
</body>
</html>
