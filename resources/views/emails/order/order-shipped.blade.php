@php
  $currency = $order->currency ?? 'USD';
  $fmt = fn($c) => number_format(max(0,(int)($c ?? 0))/100, 2);

  $meta = (array) ($order->metadata ?? []);
  $promos = (array) ($meta['promos_applied'] ?? []);
  $tracking = $meta['tracking_number'] ?? null;
@endphp

<h2 style="margin:0 0 6px;">Your order is on the way 🎉</h2>
<p style="margin:0 0 12px;">Order <strong>{{ $order->order_number }}</strong> has shipped.</p>
@if($tracking)
  <p style="margin:0 0 12px;">Tracking number: <strong>{{ $tracking }}</strong></p>
@endif

<h3 style="margin:16px 0 6px;">Items</h3>
<ul style="margin:0 0 12px; padding-left:16px;">
  @foreach($order->items as $it)
    <li>{{ $it->name }} × {{ (int)$it->quantity }} — {{ $currency }} {{ $fmt($it->total_cents ?? $it->subtotal_cents) }}</li>
  @endforeach
</ul>

@if(!empty($promos))
  <h4 style="margin:12px 0 6px;">Promotions</h4>
  <ul style="margin:0 0 12px; padding-left:16px;">
    @foreach($promos as $p)
      <li>
        {{ strtoupper($p['code'] ?? '') }} ({{ $p['type'] ?? '' }})
        @if(($p['amount_cents'] ?? 0) > 0)
          — − {{ $currency }} {{ $fmt($p['amount_cents']) }}
        @endif
      </li>
    @endforeach
  </ul>
@endif

<p style="margin:12px 0 0;">
  Subtotal: {{ $currency }} {{ $fmt($order->subtotal_cents) }}<br>
  Discount: − {{ $currency }} {{ $fmt($order->discount_cents) }}<br>
  Shipping: {{ $currency }} {{ $fmt($order->shipping_cents) }}<br>
  Tax: {{ $currency }} {{ $fmt($order->tax_cents) }}<br>
  <strong>Total: {{ $currency }} {{ $fmt($order->total_cents) }}</strong>
</p>
