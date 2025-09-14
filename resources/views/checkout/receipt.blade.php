@extends('layouts.app')

@section('content')
    @php
        $rtl = app()->getLocale() === 'ar';
        $dir = $rtl ? 'rtl' : 'ltr';

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
        ])
            ->filter()
            ->implode(', ');

        $badge = function ($status) {
            return match ($status) {
                'paid' => 'bg-green-100 text-green-800 ring-1 ring-green-300',
                'pending' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
                'failed' => 'bg-red-100 text-red-800 ring-1 ring-red-300',
                'refunded' => 'bg-blue-100 text-blue-800 ring-1 ring-blue-300',
                default => 'bg-gray-100 text-gray-800 ring-1 ring-gray-300',
            };
        };

        // Read promos from metadata (set during confirmation)
        $meta = (array) ($order->metadata ?? []);
        $promosApplied = (array) ($meta['promos_applied'] ?? []);
        $hasShipPromo = collect($promosApplied)->contains(fn($p) => ($p['type'] ?? '') === 'shipping');
        $discountPromo = collect($promosApplied)->first(
            fn($p) => in_array($p['type'] ?? '', ['fixed', 'percentage'], true),
        );
        $discountAmount = (int) ($discountPromo['amount_cents'] ?? 0);
        $discountPct =
            $discountPromo && ($discountPromo['type'] ?? '') === 'percentage'
                ? (float) ($discountPromo['percent'] ?? 0)
                : null;
        $taxCents = (int) ($order->tax_cents ?? 0);
        if ($taxCents === 0) {
            // Fallback to what we saved in metadata during checkout (manual tax flow)
            $taxCents = (int) ($meta['tax_amount_cents'] ?? 0);
        }
        $taxRateLabel = null;
        if (isset($meta['tax_rate_percent'])) {
            // Pretty print e.g. "13" or "13.5"
            $taxRateLabel = rtrim(rtrim(number_format((float) $meta['tax_rate_percent'], 2), '0'), '.');
        }

        // ---------- NEW: prefetch color/size labels for all items ----------
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
            ->keyBy('id'); // id => model

        $sizeMeta = \App\Models\ProductSize::query()->whereIn('id', $sizeIds)->pluck('size', 'id'); // id => "M"
        // -------------------------------------------------------------------

        // Identify special lines
        $isShip = fn($li) => strcasecmp(trim((string) ($li->name ?? '')), 'Shipping') === 0;
        $isTax = fn($li) => stripos(trim((string) ($li->name ?? '')), 'Tax') === 0;

        // Collections
        $allItems = collect($order->items ?? []);
        $itemLines = $allItems->reject(fn($li) => $isShip($li) || $isTax($li));

        // Display amounts (items-only subtotal/discount)
        $displayItemsSubtotalCents = (int) $itemLines->sum('subtotal_cents');
        $displayItemsDiscountCents = (int) $itemLines->sum('discount_cents');

        // Shipping (prefer order column, else fall back to summed shipping lines)
        $shippingFromLinesCents = (int) $allItems->filter($isShip)->sum('total_cents');
        $displayShippingCents = (int) ($order->shipping_cents ?? 0);
        if ($displayShippingCents === 0 && $shippingFromLinesCents > 0) {
            $displayShippingCents = $shippingFromLinesCents;
        }

        // Tax (you already compute $taxCents above via order->tax_cents or metadata)
        $taxFromLinesCents = (int) $allItems->filter($isTax)->sum('total_cents');
        if ($taxCents === 0 && $taxFromLinesCents > 0) {
            $taxCents = $taxFromLinesCents;
        }

    @endphp

    <div class="min-h-screen bg-gray-50 py-10" dir="{{ $dir }}">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="montserrat-semibold text-2xl sm:text-3xl text-black">
                        {{ __('receipt.heading', ['number' => $order->order_number]) }}
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('receipt.order_date') }}:
                        <span class="font-medium">{{ $order->created_at->translatedFormat('F j, Y') }}</span>
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-6">
                <div class="bg-white rounded-2xl shadow p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('receipt.customer') }}</h3>
                    <p class="text-gray-800">{{ $order->full_name }}</p>
                    <p class="text-gray-600 text-sm">{{ $order->email }}</p>
                    @if ($order->phone)
                        <p class="text-gray-600 text-sm mt-1">{{ $order->phone }}</p>
                    @endif
                </div>

                <div class="bg-white rounded-2xl shadow p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('receipt.shipping_address') }}</h3>
                    <p class="text-gray-800 text-sm leading-6">
                        {{ $addr($ship) ?: '—' }}
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('receipt.billing_address') }}</h3>
                    <p class="text-gray-800 text-sm leading-6">
                        {{ $addr($bill) ?: '—' }}
                    </p>
                </div>
            </div>

            {{-- Promotions --}}
            <div class="mt-6">
                <div class="bg-white rounded-2xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('receipt.promotions') }}</h3>
                        @if (empty($promosApplied))
                            <span class="text-xs text-gray-500">{{ __('receipt.no_promotions') }}</span>
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
                                    <div class="flex items-center gap-2">
                                        @if ($type === 'shipping')
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                Free Shipping
                                            </span>
                                            <span class="text-sm text-gray-700">Code: <span
                                                    class="font-mono">{{ $code }}</span></span>
                                        @elseif($type === 'percentage')
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                {{ (float) $percent }}% Off
                                            </span>
                                            <span class="text-sm text-gray-700">
                                                Code: <span class="font-mono">{{ $code }}</span>
                                                @if ($amountCents > 0)
                                                    · <span class="text-gray-500">− {{ $currency }}
                                                        {{ $fmt($amountCents) }}</span>
                                                @endif
                                            </span>
                                        @elseif($type === 'fixed')
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                Amount Off
                                            </span>
                                            <span class="text-sm text-gray-700">
                                                Code: <span class="font-mono">{{ $code }}</span>
                                                @if ($amountCents > 0)
                                                    · <span class="text-gray-500">− {{ $currency }}
                                                        {{ $fmt($amountCents) }}</span>
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
            <div class="bg-white rounded-2xl shadow mt-6 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('receipt.items') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-gray-600">
                                <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_product') }}</th>
                                <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_qty') }}</th>
                                <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_price') }}</th>
                                <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($order->items as $item)
                                @php
                                    // Snapshot + variant IDs
                                    $snap = (array) ($item->snapshot ?? []);
                                    $variant = (array) ($snap['variant'] ?? []);
                                    $thumb = $snap['image_url'] ?? null;

                                    $colorId = $variant['color_id'] ?? ($snap['color_id'] ?? ($item->color_id ?? null));
                                    $sizeId = $variant['size_id'] ?? ($snap['size_id'] ?? ($item->size_id ?? null));

                                    // Resolve color (prefer ID -> DB)
                                    $swatchHex = null;
                                    $colorLabel = null;
                                    if ($colorId && $colorMeta->has($colorId)) {
                                        $c = $colorMeta[$colorId];
                                        $swatchHex = strtoupper((string) $c->color_code);
                                        $colorLabel = $c->name ?: $swatchHex;
                                    } else {
                                        // Fallback to hex/name in snapshot or item
                                        $hexFromSnap =
                                            $snap['color_code'] ?? ($snap['color'] ?? ($item->color_code ?? null));
                                        if (
                                            $hexFromSnap &&
                                            !str_starts_with($hexFromSnap, '#') &&
                                            preg_match('/^[0-9A-Fa-f]{6}$/', $hexFromSnap)
                                        ) {
                                            $hexFromSnap = '#' . $hexFromSnap;
                                        }
                                        $swatchHex = $hexFromSnap ?: null;
                                        $colorLabel = $snap['color_name'] ?? ($snap['colorLabel'] ?? $swatchHex);
                                    }

                                    // Resolve size (prefer ID -> DB)
                                    $sizeLabel = null;
                                    if ($sizeId && $sizeMeta->has($sizeId)) {
                                        $sizeLabel = (string) $sizeMeta[$sizeId];
                                    } else {
                                        $sizeLabel = $snap['size'] ?? ($item->size ?? null);
                                    }

                                    // Money
                                    $unitCents = (int) ($item->unit_price_cents ?? 0);
                                    $subCents = (int) ($item->subtotal_cents ?? $unitCents * (int) $item->quantity);
                                    $discCents = (int) ($item->discount_cents ?? 0);
                                    $totalCents = (int) ($item->total_cents ?? max(0, $subCents - $discCents));
                                @endphp
                                <tr class="text-gray-900 align-top">
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-3">
                                            @if ($thumb)
                                                <img src="{{ $thumb }}" alt=""
                                                    class="w-10 h-10 rounded object-cover ring-1 ring-gray-200">
                                            @endif
                                            <div>
                                                <div class="font-medium">
                                                    {{ $item->name ?? ($item->product->name ?? __('receipt.deleted_item')) }}
                                                </div>

                                                {{-- Variant badges (name + swatch / size) --}}
                                                @if ($swatchHex || $sizeLabel)
                                                    <div class="mt-1 flex items-center gap-3 text-xs text-gray-600">
                                                        @if ($swatchHex)
                                                            <span class="inline-flex items-center gap-1.5">
                                                                <span
                                                                    class="inline-block w-3.5 h-3.5 rounded-full ring-1 ring-gray-300"
                                                                    style="background: {{ $swatchHex }};"></span>
                                                                <span>{{ $colorLabel }}</span>
                                                            </span>
                                                        @endif
                                                        @if ($sizeLabel)
                                                            <span
                                                                class="inline-flex items-center px-1.5 py-0.5 rounded border border-gray-300 bg-gray-50">
                                                                {{ strtoupper($sizeLabel) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif

                                                @if ($item->sku)
                                                    <div class="text-xs text-gray-500 mt-1">{{ __('receipt.sku') }}:
                                                        {{ $item->sku }}</div>
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
                                                <span class="line-through text-gray-400">{{ $currency }}
                                                    {{ $fmt($subCents) }}</span>
                                                <span class="text-emerald-700 font-medium"> {{ $currency }}
                                                    {{ $fmt($totalCents) }}</span>
                                                <span class="text-xs text-emerald-700">− {{ $currency }}
                                                    {{ $fmt($discCents) }}</span>
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
                                <td colspan="3" class="px-5 py-3 text-right text-gray-600">{{ __('receipt.subtotal') }}
                                </td>
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    {{ $currency }} {{ $fmt($displayItemsSubtotalCents) }}
                                </td>
                            </tr>
                            @if ($displayItemsDiscountCents > 0)
  <tr>
    <td colspan="3" class="px-5 py-3 text-right text-gray-600">{{ __('receipt.discount') }}</td>
    <td class="px-5 py-3 font-medium text-emerald-700">
      − {{ $currency }} {{ $fmt($displayItemsDiscountCents) }}
      @if($discountPromo)
        <span class="ml-2 inline-flex items-center gap-1 text-xs text-emerald-700">
          <span class="px-1.5 py-0.5 rounded bg-emerald-100">CODE</span>
          <span class="font-mono">{{ strtoupper($discountPromo['code'] ?? '') }}</span>
          @if($discountPct) <span>({{ rtrim(rtrim(number_format($discountPct, 2), '0'), '.') }}%)</span> @endif
        </span>
      @endif
    </td>
  </tr>
@endif
<tr>
  <td colspan="3" class="px-5 py-3 text-right text-gray-600">{{ __('receipt.shipping') }}</td>
  <td class="px-5 py-3">
    @if ($hasShipPromo && $displayShippingCents === 0)
      <span class="inline-flex items-center gap-2">
        <span class="text-emerald-700 font-medium">{{ __('Free') }}</span>
        <span class="px-1.5 py-0.5 text-xs rounded bg-emerald-100 text-emerald-800">PROMO</span>
      </span>
    @else
      <span class="font-medium text-gray-900">
        {{ $currency }} {{ $fmt($displayShippingCents) }}
      </span>
    @endif
  </td>
</tr>


                            @if ($taxCents > 0)
                                <tr>
                                    <td colspan="3" class="px-5 py-3 text-right text-gray-600">
                                        {{ __('receipt.tax') }}@if ($taxRateLabel)
                                            ({{ $taxRateLabel }}%)
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-medium text-gray-900">
                                        {{ $currency }} {{ $fmt($taxCents) }}
                                    </td>
                                </tr>
                            @endif


                            <tr>
                                <td colspan="4" class="px-5 pt-2">
                                    <div class="h-px bg-gray-200"></div>
                                </td>
                            </tr>

                            <tr class="bg-gray-100">
                                <td colspan="3"
                                    class="px-5 py-4 text-right text-gray-900 text-base sm:text-lg font-semibold">
                                    Total
                                </td>
                                <td class="px-5 py-4 text-gray-900 text-base sm:text-lg font-semibold">
                                    {{ $currency }} {{ $fmt($order->total_cents) }}
                                </td>
                            </tr>

                            @if ((int) $order->discount_cents > 0)
                                <tr>
                                    <td colspan="4" class="px-5 pb-4 text-right">
                                        <span class="text-xs text-emerald-700">
                                            {{ __('You saved') }} {{ $currency }} {{ $fmt($order->discount_cents) }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Actions --}}
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('home') }}"
                    class="inline-flex items-center px-4 py-2 rounded-md bg-black text-white hover:bg-black/90">
                    {{ __('receipt.back_to_shop') }}
                </a>
                <button onclick="window.print()"
                    class="inline-flex items-center px-4 py-2 rounded-md ring-1 ring-gray-300 text-gray-800 hover:bg-gray-100">
                    {{ __('receipt.print') }}
                </button>
            </div>

            <p class="text-xs text-gray-500 mt-6">
                {{ __('receipt.footer_note') }}
            </p>
        </div>
    </div>
@endsection
