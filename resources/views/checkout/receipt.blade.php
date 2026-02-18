@extends('layouts.app')

@push('head')
<style>
  /* =========================
     Subtle Success Check (no libs)
     ========================= */
  .success-check-wrap{
    width: 68px;
    height: 68px;
    display:grid;
    place-items:center;
    border-radius: 999px;
    position: relative;
    isolation: isolate;
    background: radial-gradient(closest-side, rgba(16,185,129,.10), rgba(16,185,129,.04), transparent 70%);
  }

  .success-check{
    width:68px;
    height:68px;
    filter: drop-shadow(0 10px 16px rgba(17,24,39,.08));
  }

  .success-circle{
    stroke: rgba(16,185,129,.55);
    stroke-width: 5;
    stroke-linecap: round;
    stroke-dasharray: 176;
    stroke-dashoffset: 176;
    animation: circleDraw .75s cubic-bezier(.2,.9,.2,1) .05s forwards;
  }

  .success-tick{
    stroke: rgba(16,185,129,.78);
    stroke-width: 6;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 60;
    stroke-dashoffset: 60;
    transform-origin: 36px 36px;
    animation:
      tickDraw .42s cubic-bezier(.2,.9,.2,1) .62s forwards,
      tickPop .26s ease-out .98s forwards;
  }

  .success-pulse{
    position:absolute;
    inset: -10px;
    border-radius: 999px;
    border: 2px solid rgba(16,185,129,.14);
    opacity: 0;
    transform: scale(.92);
    animation: pulseRing .75s ease-out .70s forwards;
    z-index:-1;
  }

  .success-sparkles{
    position:absolute;
    inset:-12px;
    border-radius:999px;
    background:
      radial-gradient(circle at 20% 30%, rgba(16,185,129,.18) 0 2px, transparent 3px),
      radial-gradient(circle at 78% 28%, rgba(16,185,129,.14) 0 2px, transparent 3px),
      radial-gradient(circle at 30% 78%, rgba(16,185,129,.14) 0 2px, transparent 3px),
      radial-gradient(circle at 82% 75%, rgba(16,185,129,.18) 0 2px, transparent 3px);
    opacity: 0;
    transform: scale(.96);
    animation: sparklePop .45s ease-out .88s forwards;
    z-index:-1;
  }

  @keyframes circleDraw{ from { stroke-dashoffset:176; } to { stroke-dashoffset:0; } }
  @keyframes tickDraw{ from { stroke-dashoffset:60; } to { stroke-dashoffset:0; } }
  @keyframes tickPop{ from { transform: scale(1); } to { transform: scale(1.03); } }
  @keyframes pulseRing{
    0%   { opacity: 0; transform: scale(.92); }
    35%  { opacity: 1; }
    100% { opacity: 0; transform: scale(1.06); }
  }
  @keyframes sparklePop{ from { opacity:0; transform: scale(.96); } to { opacity:1; transform: scale(1); } }

  /* Entrance */
  .receipt-enter{ animation: receiptEnter .45s ease-out both; }
  @keyframes receiptEnter{ from{ opacity:0; transform: translateY(8px); } to{ opacity:1; transform: translateY(0); } }

  /* Reduced motion */
  @media (prefers-reduced-motion: reduce){
    .success-circle,.success-tick,.success-pulse,.success-sparkles,.receipt-enter{ animation:none !important; }
  }
</style>
@endpush

@section('content')
  @php
      // ✅ No locale/lang usage anywhere
      $dir = 'ltr';

      // Safe getters
      $ship = (array) ($order->shipping_address_json ?? []);
      $bill = (array) ($order->billing_address_json ?? []);
      $currency = $order->currency ?? 'USD';

      $fmt = fn($cents) => number_format(max(0, (int) ($cents ?? 0)) / 100, 2);
      $addr = fn($a) => collect([
          $a['line1'] ?? null,
          $a['line2'] ?? null,
          $a['city'] ?? null,
          $a['state'] ?? null,
          $a['postal_code'] ?? null,
          $a['country'] ?? null,
      ])->filter()->implode(', ');

      $badge = function ($status) {
          return match ($status) {
              'paid' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
              'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
              'failed' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-100',
              'refunded' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-100',
              default => 'bg-gray-50 text-gray-700 ring-1 ring-gray-200',
          };
      };

      // Read promos from metadata
      $meta = (array) ($order->metadata ?? []);
      $promosApplied = (array) ($meta['promos_applied'] ?? []);
      $hasShipPromo = collect($promosApplied)->contains(fn($p) => ($p['type'] ?? '') === 'shipping');

      $discountPromo = collect($promosApplied)->first(
          fn($p) => in_array($p['type'] ?? '', ['fixed', 'percentage'], true),
      );
      $discountPct =
          $discountPromo && ($discountPromo['type'] ?? '') === 'percentage'
              ? (float) ($discountPromo['percent'] ?? 0)
              : null;

      $taxCents = (int) ($order->tax_cents ?? 0);
      if ($taxCents === 0) {
          $taxCents = (int) ($meta['tax_amount_cents'] ?? 0);
      }
      $taxRateLabel = null;
      if (isset($meta['tax_rate_percent'])) {
          $taxRateLabel = rtrim(rtrim(number_format((float) $meta['tax_rate_percent'], 2), '0'), '.');
      }

      // Prefetch color/size
      $items = $order->items ?? collect();

      $colorIds = collect($items)
          ->flatMap(function ($it) {
              $snap = (array) ($it->snapshot ?? []);
              $v = (array) ($snap['variant'] ?? []);
              return [(int) ($v['color_id'] ?? ($snap['color_id'] ?? ($it->color_id ?? 0)))];
          })
          ->filter()
          ->unique()
          ->values();

      $sizeIds = collect($items)
          ->flatMap(function ($it) {
              $snap = (array) ($it->snapshot ?? []);
              $v = (array) ($snap['variant'] ?? []);
              return [(int) ($v['size_id'] ?? ($snap['size_id'] ?? ($it->size_id ?? 0)))];
          })
          ->filter()
          ->unique()
          ->values();

      $colorMeta = \App\Models\ProductColor::query()
          ->whereIn('id', $colorIds)
          ->get(['id', 'name', 'color_code'])
          ->keyBy('id');

      $sizeMeta = \App\Models\ProductSize::query()
          ->whereIn('id', $sizeIds)
          ->pluck('size', 'id');

      // Identify special lines
      $isShip = fn($li) => strcasecmp(trim((string) ($li->name ?? '')), 'Shipping') === 0;
      $isTax = fn($li) => stripos(trim((string) ($li->name ?? '')), 'Tax') === 0;

      // Collections
      $allItems = collect($order->items ?? []);
      $itemLines = $allItems->reject(fn($li) => $isShip($li) || $isTax($li));

      // Display amounts
      $displayItemsSubtotalCents = (int) $itemLines->sum('subtotal_cents');
      $displayItemsDiscountCents = (int) $itemLines->sum('discount_cents');

      // Shipping
      $shippingFromLinesCents = (int) $allItems->filter($isShip)->sum('total_cents');
      $displayShippingCents = (int) ($order->shipping_cents ?? 0);
      if ($displayShippingCents === 0 && $shippingFromLinesCents > 0) {
          $displayShippingCents = $shippingFromLinesCents;
      }

      // Tax from lines fallback
      $taxFromLinesCents = (int) $allItems->filter($isTax)->sum('total_cents');
      if ($taxCents === 0 && $taxFromLinesCents > 0) {
          $taxCents = $taxFromLinesCents;
      }
  @endphp

  <div class="min-h-screen bg-gradient-to-b from-gray-50 via-white to-gray-50 py-10" dir="{{ $dir }}">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

      {{-- Success Banner (no buttons) --}}
      <div class="mb-6 receipt-enter">
        <div class="relative overflow-hidden rounded-3xl bg-white shadow border border-gray-100">
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-emerald-50/40 via-white to-white"></div>

          <div class="relative p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-5">
            <div class="shrink-0">
              <div class="success-check-wrap" aria-hidden="true">
                <div class="success-pulse"></div>
                <svg class="success-check" viewBox="0 0 72 72" fill="none">
                  <circle class="success-circle" cx="36" cy="36" r="28" />
                  <path class="success-tick" d="M22 37.5L32 47.5L50 28.5" />
                </svg>
                <div class="success-sparkles"></div>
              </div>
            </div>

            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-semibold text-gray-900">
                  Order placed successfully
                </h2>

                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                  Confirmed
                </span>
              </div>

              <p class="mt-2 text-sm text-gray-600 leading-6">
                Thanks {{ $order->full_name }} — we’ve received your order
                <span class="font-semibold text-gray-900">#{{ $order->order_number }}</span>.
                A confirmation email has been sent to <span class="font-medium">{{ $order->email }}</span>.
              </p>

              <div class="mt-4 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs bg-gray-50 text-gray-700 ring-1 ring-gray-200">
                  <span class="opacity-70">Date</span>
                  <span class="font-semibold text-gray-900">{{ $order->created_at->format('F j, Y') }}</span>
                </span>

                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs bg-gray-50 text-gray-700 ring-1 ring-gray-200">
                  <span class="opacity-70">Total</span>
                  <span class="font-semibold text-gray-900">{{ $currency }} {{ $fmt($order->total_cents) }}</span>
                </span>

                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs bg-gray-50 text-gray-700 ring-1 ring-gray-200">
                  <span class="opacity-70">Payment</span>
                  <span class="font-semibold text-gray-900">{{ ucfirst($order->payment_status) }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Header --}}
      <div class="flex items-center justify-between gap-4 receipt-enter">
        <div>
          <h1 class="montserrat-semibold text-2xl sm:text-3xl text-gray-900">
            Receipt #{{ $order->order_number }}
          </h1>
          <p class="text-sm text-gray-600 mt-1">
            Order date: <span class="font-medium">{{ $order->created_at->format('F j, Y') }}</span>
          </p>
        </div>

        <div class="text-right">
          <div class="flex items-center gap-2 justify-end">
            <span class="text-xs px-2 py-1 rounded-full {{ $badge($order->payment_status) }}">
              {{ ucfirst($order->payment_status) }}
            </span>
            <span class="text-xs px-2 py-1 rounded-full {{ $badge($order->order_status) }}">
              {{ ucfirst($order->order_status) }}
            </span>
          </div>
        </div>
      </div>

      {{-- Customer / Addresses --}}
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-6 receipt-enter">
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
          <h3 class="text-sm font-semibold text-gray-900 mb-2">Customer</h3>
          <p class="text-gray-800">{{ $order->full_name }}</p>
          <p class="text-gray-600 text-sm">{{ $order->email }}</p>
          @if ($order->phone)
            <p class="text-gray-600 text-sm mt-1">{{ $order->phone }}</p>
          @endif
        </div>

        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
          <h3 class="text-sm font-semibold text-gray-900 mb-2">Shipping address</h3>
          <p class="text-gray-800 text-sm leading-6">{{ $addr($ship) ?: '—' }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
          <h3 class="text-sm font-semibold text-gray-900 mb-2">Billing address</h3>
          <p class="text-gray-800 text-sm leading-6">{{ $addr($bill) ?: '—' }}</p>
        </div>
      </div>

      {{-- Promotions --}}
      <div class="mt-6 receipt-enter">
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Promotions</h3>
            @if (empty($promosApplied))
              <span class="text-xs text-gray-500">None</span>
            @endif
          </div>

          @if (!empty($promosApplied))
            <div class="mt-3 space-y-2">
              @foreach ($promosApplied as $p)
                @php
                  $type = $p['type'] ?? '';
                  $code = strtoupper($p['code'] ?? '');
                  $amountCents = (int) ($p['amount_cents'] ?? 0);
                  $percent = $p['percent'] ?? null;
                @endphp
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2 flex-wrap">
                    @if ($type === 'shipping')
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                        Free Shipping
                      </span>
                      <span class="text-sm text-gray-700">Code: <span class="font-mono">{{ $code }}</span></span>
                    @elseif($type === 'percentage')
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100">
                        {{ (float) $percent }}% Off
                      </span>
                      <span class="text-sm text-gray-700">
                        Code: <span class="font-mono">{{ $code }}</span>
                        @if ($amountCents > 0)
                          · <span class="text-gray-500">− {{ $currency }} {{ $fmt($amountCents) }}</span>
                        @endif
                      </span>
                    @elseif($type === 'fixed')
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100">
                        Amount Off
                      </span>
                      <span class="text-sm text-gray-700">
                        Code: <span class="font-mono">{{ $code }}</span>
                        @if ($amountCents > 0)
                          · <span class="text-gray-500">− {{ $currency }} {{ $fmt($amountCents) }}</span>
                        @endif
                      </span>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      {{-- Items --}}
      <div class="bg-white rounded-2xl shadow mt-6 overflow-hidden border border-gray-100 receipt-enter">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="text-base font-semibold text-gray-900">Items</h3>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
              <tr class="text-gray-600">
                <th class="px-5 py-3 text-left font-medium">Product</th>
                <th class="px-5 py-3 text-left font-medium">Qty</th>
                <th class="px-5 py-3 text-left font-medium">Price</th>
                <th class="px-5 py-3 text-left font-medium">Subtotal</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 bg-white">
              @foreach ($order->items as $item)
                @php
                  $snap = (array) ($item->snapshot ?? []);
                  $variant = (array) ($snap['variant'] ?? []);
                  $thumb = $snap['image_url'] ? asset('storage/' .$snap['image_url']) : null;

                  $colorId = $variant['color_id'] ?? ($snap['color_id'] ?? ($item->color_id ?? null));
                  $sizeId  = $variant['size_id'] ?? ($snap['size_id'] ?? ($item->size_id ?? null));

                  $swatchHex = null;
                  $colorLabel = null;

                  if ($colorId && $colorMeta->has($colorId)) {
                    $c = $colorMeta[$colorId];
                    $swatchHex = strtoupper((string) $c->color_code);
                    $colorLabel = $c->name ?: $swatchHex;
                  } else {
                    $hexFromSnap = $snap['color_code'] ?? ($snap['color'] ?? ($item->color_code ?? null));
                    if ($hexFromSnap && !str_starts_with($hexFromSnap, '#') && preg_match('/^[0-9A-Fa-f]{6}$/', $hexFromSnap)) {
                      $hexFromSnap = '#' . $hexFromSnap;
                    }
                    $swatchHex = $hexFromSnap ?: null;
                    $colorLabel = $snap['color_name'] ?? ($snap['colorLabel'] ?? $swatchHex);
                  }

                  $sizeLabel = null;
                  if ($sizeId && $sizeMeta->has($sizeId)) {
                    $sizeLabel = (string) $sizeMeta[$sizeId];
                  } else {
                    $sizeLabel = $snap['size'] ?? ($item->size ?? null);
                  }

                  $unitCents = (int) ($item->unit_price_cents ?? 0);
                  $subCents  = (int) ($item->subtotal_cents ?? $unitCents * (int) $item->quantity);
                  $discCents = (int) ($item->discount_cents ?? 0);
                  $totalCents = (int) ($item->total_cents ?? max(0, $subCents - $discCents));
                @endphp

                <tr class="text-gray-900 align-top">
                  <td class="px-5 py-4">
                    <div class="flex items-start gap-3">
                      @if ($thumb)
                        <img src="{{ $thumb }}" alt="" class="h-14 rounded object-contain">
                      @endif

                      <div>
                        <div class="font-medium">
                          {{ $item->name ?? ($item->product->name ?? 'Item') }}
                        </div>

                        @if ($swatchHex || $sizeLabel)
                          <div class="mt-1 flex items-center gap-3 text-xs text-gray-600">
                            @if ($swatchHex)
                              <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block w-3.5 h-3.5 rounded-full ring-1 ring-gray-300" style="background: {{ $swatchHex }};"></span>
                                <span>{{ $colorLabel }}</span>
                              </span>
                            @endif
                            @if ($sizeLabel)
                              <span class="inline-flex items-center px-1.5 py-0.5 rounded border border-gray-300 bg-gray-50">
                                {{ strtoupper($sizeLabel) }}
                              </span>
                            @endif
                          </div>
                        @endif

                        @if ($item->sku)
                          <div class="text-xs text-gray-500 mt-1">SKU: {{ $item->sku }}</div>
                        @endif
                      </div>
                    </div>
                  </td>

                  <td class="px-5 py-4 whitespace-nowrap">{{ (int) $item->quantity }}</td>

                  <td class="px-5 py-4 whitespace-nowrap">
                    {{ $currency }} {{ $fmt($unitCents) }}
                  </td>

                  <td class="px-5 py-4 whitespace-nowrap">
                    @if ($discCents > 0)
                      <div class="flex flex-col items-end">
                        <span class="line-through text-gray-400">{{ $currency }} {{ $fmt($subCents) }}</span>
                        <span class="text-emerald-700 font-medium">{{ $currency }} {{ $fmt($totalCents) }}</span>
                        <span class="text-xs text-emerald-700">− {{ $currency }} {{ $fmt($discCents) }}</span>
                      </div>
                    @else
                      {{ $currency }} {{ $fmt($subCents) }}
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>

            <tfoot class="bg-gray-50">
              <tr>
                <td colspan="3" class="px-5 py-3 text-right text-gray-600">Subtotal</td>
                <td class="px-5 py-3 font-medium text-gray-900">{{ $currency }} {{ $fmt($displayItemsSubtotalCents) }}</td>
              </tr>

              @if ($displayItemsDiscountCents > 0)
                <tr>
                  <td colspan="3" class="px-5 py-3 text-right text-gray-600">Discount</td>
                  <td class="px-5 py-3 font-medium text-emerald-700">
                    − {{ $currency }} {{ $fmt($displayItemsDiscountCents) }}
                    @if ($discountPromo)
                      <span class="ml-2 inline-flex items-center gap-1 text-xs text-emerald-700">
                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 ring-1 ring-emerald-100">CODE</span>
                        <span class="font-mono">{{ strtoupper($discountPromo['code'] ?? '') }}</span>
                        @if ($discountPct)
                          <span>({{ rtrim(rtrim(number_format($discountPct, 2), '0'), '.') }}%)</span>
                        @endif
                      </span>
                    @endif
                  </td>
                </tr>
              @endif

              <tr>
                <td colspan="3" class="px-5 py-3 text-right text-gray-600">Shipping</td>
                <td class="px-5 py-3">
                  @if ($hasShipPromo && $displayShippingCents === 0)
                    <span class="inline-flex items-center gap-2">
                      <span class="text-emerald-700 font-medium">Free</span>
                      <span class="px-1.5 py-0.5 text-xs rounded bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">PROMO</span>
                    </span>
                  @else
                    <span class="font-medium text-gray-900">{{ $currency }} {{ $fmt($displayShippingCents) }}</span>
                  @endif
                </td>
              </tr>

              @if ($taxCents > 0)
                <tr>
                  <td colspan="3" class="px-5 py-3 text-right text-gray-600">
                    Tax @if ($taxRateLabel) ({{ $taxRateLabel }}%) @endif
                  </td>
                  <td class="px-5 py-3 font-medium text-gray-900">{{ $currency }} {{ $fmt($taxCents) }}</td>
                </tr>
              @endif

              <tr>
                <td colspan="4" class="px-5 pt-2"><div class="h-px bg-gray-200"></div></td>
              </tr>

              <tr class="bg-gray-100">
                <td colspan="3" class="px-5 py-4 text-right text-gray-900 text-base sm:text-lg font-semibold">Total</td>
                <td class="px-5 py-4 text-gray-900 text-base sm:text-lg font-semibold">
                  {{ $currency }} {{ $fmt($order->total_cents) }}
                </td>
              </tr>

              @if ((int) $order->discount_cents > 0)
                <tr>
                  <td colspan="4" class="px-5 pb-4 text-right">
                    <span class="text-xs text-emerald-700">You saved {{ $currency }} {{ $fmt($order->discount_cents) }}</span>
                  </td>
                </tr>
              @endif
            </tfoot>
          </table>
        </div>
      </div>

      <p class="text-xs text-gray-500 mt-6 receipt-enter">
        If you have any questions, reply to your confirmation email with your order number.
      </p>

    </div>
  </div>
@endsection
