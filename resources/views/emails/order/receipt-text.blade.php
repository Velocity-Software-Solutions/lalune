Receipt {{ $order->order_number }}

Date: {{ $order->created_at->format('M j, Y g:i A') }}
Status: {{ $order->payment_status }}

Items:
@foreach($order->items as $it)
- {{ $it->name }} x{{ (int)$it->quantity }}  @ {{ $order->currency }} {{ number_format(($it->unit_price_cents ?? 0)/100, 2) }}
@endforeach

Promotions:
@forelse($promosApplied as $p)
- {{ strtoupper($p['code'] ?? '') }} ({{ $p['type'] ?? '' }})
@empty
- None
@endforelse

Subtotal:  {{ $order->currency }} {{ number_format(($order->subtotal_cents ?? 0)/100, 2) }}
Discount:  {{ $order->currency }} {{ number_format(($order->discount_cents ?? 0)/100, 2) }}
Shipping:  {{ $order->currency }} {{ number_format(($order->shipping_cents ?? 0)/100, 2) }}
Tax:       {{ $order->currency }} {{ number_format(($order->tax_cents ?? 0)/100, 2) }}
Total:     {{ $order->currency }} {{ number_format(($order->total_cents ?? 0)/100, 2) }}

Thank you!
