@php
    $currency = $order->currency ?? 'USD';
    $fmt = fn($cents) => number_format(max(0, (int) ($cents ?? 0)) / 100, 2);

    // Pull promos (optional, used above)
    $meta = (array) ($order->metadata ?? []);
    $promosApplied = (array) ($meta['promos_applied'] ?? []);

    // Helpers to detect special lines
    $isShip = fn($li) => strcasecmp(trim((string) ($li->name ?? '')), 'Shipping') === 0;
    $isTax = fn($li) => stripos(trim((string) ($li->name ?? '')), 'Tax') === 0;

    $allItems = collect($order->items ?? []);
    $itemLines = $allItems->reject(fn($li) => $isShip($li) || $isTax($li));

    // Display amounts (items-only)
    $displayItemsSubtotalCents = (int) $itemLines->sum('subtotal_cents');
    $displayItemsDiscountCents = (int) $itemLines->sum('discount_cents');

    // Shipping: prefer order column; if it's 0, fall back to a Shipping line item
$displayShippingCents = (int) ($order->shipping_cents ?? 0);
$shippingFromLinesCents = (int) $allItems->filter($isShip)->sum('total_cents');
if ($displayShippingCents === 0 && $shippingFromLinesCents > 0) {
    $displayShippingCents = $shippingFromLinesCents;
}

// Tax: prefer order column; else metadata; else Tax line items
$displayTaxCents = (int) ($order->tax_cents ?? 0);
if ($displayTaxCents === 0) {
    $displayTaxCents = (int) ($meta['tax_amount_cents'] ?? 0);
}
if ($displayTaxCents === 0) {
    $displayTaxCents = (int) $allItems->filter($isTax)->sum('total_cents');
    }
@endphp
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->order_number }}</title>
</head>

<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#111;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:20px 24px;border-bottom:1px solid #eee;">
                            <h1 style="margin:0;font-size:18px;">Thanks for your order! 🎉</h1>
                            <p style="margin:6px 0 0 0;font-size:13px;color:#555;">Order
                                <strong>{{ $order->order_number }}</strong> •
                                {{ $order->created_at->format('M j, Y g:i A') }}</p>
                        </td>
                    </tr>

                    {{-- Items --}}
                    <tr>
                        <td style="padding:16px 24px;">
                            <h3 style="margin:0 0 10px 0;font-size:15px;">Items</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th align="left"
                                            style="font-size:12px;color:#666;padding:8px 0;border-bottom:1px solid #eee;">
                                            Product</th>
                                        <th align="right"
                                            style="font-size:12px;color:#666;padding:8px 0;border-bottom:1px solid #eee;">
                                            Qty</th>
                                        <th align="right"
                                            style="font-size:12px;color:#666;padding:8px 0;border-bottom:1px solid #eee;">
                                            Unit</th>
                                        <th align="right"
                                            style="font-size:12px;color:#666;padding:8px 0;border-bottom:1px solid #eee;">
                                            Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->items as $it)
                                        @php
                                            $unit = (int) ($it->unit_price_cents ?? 0);
                                            $sub = (int) ($it->subtotal_cents ?? $unit * (int) $it->quantity);
                                            $disc = (int) ($it->discount_cents ?? 0);
                                            $tot = (int) ($it->total_cents ?? max(0, $sub - $disc));
                                        @endphp
                                        <tr>
                                            <td style="padding:8px 0;font-size:13px;color:#111;">
                                                {{ $it->name ?? 'Item' }}
                                                @if ($it->sku)
                                                    <div style="font-size:11px;color:#888;">SKU: {{ $it->sku }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td align="right" style="padding:8px 0;font-size:13px;color:#111;">
                                                {{ (int) $it->quantity }}</td>
                                            <td align="right" style="padding:8px 0;font-size:13px;color:#111;">
                                                {{ $currency }} {{ $fmt($unit) }}</td>
                                            <td align="right" style="padding:8px 0;font-size:13px;color:#111;">
                                                @if ($disc > 0)
                                                    <div><s style="color:#999;">{{ $currency }}
                                                            {{ $fmt($sub) }}</s></div>
                                                    <div style="color:#0a7e55;font-weight:bold;">{{ $currency }}
                                                        {{ $fmt($tot) }}</div>
                                                    <div style="color:#0a7e55;font-size:11px;">− {{ $currency }}
                                                        {{ $fmt($disc) }}</div>
                                                @else
                                                    {{ $currency }} {{ $fmt($sub) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    {{-- Promotions --}}
                    <tr>
                        <td style="padding:0 24px 8px 24px;">
                            <h3 style="margin:0 0 10px 0;font-size:15px;">Promotions</h3>
                            @if (empty($promosApplied))
                                <p style="margin:0 0 8px 0;font-size:13px;color:#666;">No promotions applied.</p>
                            @else
                                @foreach ($promosApplied as $p)
                                    @php
                                        $type = $p['type'] ?? '';
                                        $code = strtoupper($p['code'] ?? '');
                                        $amountC = (int) ($p['amount_cents'] ?? 0);
                                        $percent = $p['percent'] ?? null;
                                    @endphp
                                    <div style="font-size:13px;color:#111;margin:6px 0;">
                                        @if ($type === 'shipping')
                                            <strong>Free Shipping</strong> — Code: <span
                                                style="font-family:monospace">{{ $code }}</span>
                                        @elseif($type === 'percentage')
                                            <strong>{{ (float) $percent }}% Off</strong> — Code: <span
                                                style="font-family:monospace">{{ $code }}</span>
                                            @if ($amountC > 0)
                                                <span style="color:#0a7e55;">(− {{ $currency }}
                                                    {{ $fmt($amountC) }})</span>
                                            @endif
                                        @elseif($type === 'fixed')
                                            <strong>Amount Off</strong> — Code: <span
                                                style="font-family:monospace">{{ $code }}</span>
                                            @if ($amountC > 0)
                                                <span style="color:#0a7e55;">(− {{ $currency }}
                                                    {{ $fmt($amountC) }})</span>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </td>
                    </tr>

                    {{-- Totals --}}
                    <tr>
                        <td style="padding:12px 24px 20px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td align="right" style="font-size:13px;color:#666;padding:4px 0;">Subtotal</td>
                                    <td align="right" style="font-size:13px;color:#111;padding:4px 0;">
                                        {{ $currency }} {{ $fmt($displayItemsSubtotalCents) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="font-size:13px;color:#666;padding:4px 0;">Discount</td>
                                    <td align="right" style="font-size:13px;color:#0a7e55;padding:4px 0;">
                                        − {{ $currency }} {{ $fmt($displayItemsDiscountCents) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="font-size:13px;color:#666;padding:4px 0;">Shipping</td>
                                    <td align="right" style="font-size:13px;color:#111;padding:4px 0;">
                                        {{ $currency }} {{ $fmt($displayShippingCents) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="font-size:13px;color:#666;padding:4px 0;">Tax</td>
                                    <td align="right" style="font-size:13px;color:#111;padding:4px 0;">
                                        {{ $currency }} {{ $fmt($displayTaxCents) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right"
                                        style="font-size:14px;color:#111;padding-top:8px;font-weight:bold;">Total</td>
                                    <td align="right"
                                        style="font-size:14px;color:#111;padding-top:8px;font-weight:bold;">
                                        {{ $currency }} {{ $fmt($order->total_cents) }}
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:16px 24px;border-top:1px solid #eee;font-size:12px;color:#666;">
                            If you have any questions, reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
