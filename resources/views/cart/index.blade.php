@extends('layouts.app')

@section('content')
    <div class="px-4 py-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <h1 class="mb-6 text-3xl font-bold text-black">{{ __('cart.title') }}</h1>
        <form action="{{ route('cart.applyPromo') }}" method="POST" class="py-4">
            @csrf
            <label for="promo" class="mr-2">Have a promo?</label>
            <input id="promo" type="text" name="promo_code" placeholder="Enter promo code"
                class="p-2 border border-black/30 rounded focus:outline-none focus:ring-2 focus:ring-black/20 focus:border-black">
            <button type="submit"
                class="px-4 py-2 text-white bg-black hover:bg-black/90 focus:ring-2 focus:ring-black/40 transition rounded">
                Apply
            </button>
        </form>

        @if (session('cart') && count(session('cart')) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg shadow border border-white/60">
                    <thead>
                        <tr class="text-sm font-semibold text-left text-black bg-white">
                            <th class="px-6 py-4">{{ __('cart.th_product') }}</th>
                            <th class="px-6 py-4">{{ __('cart.th_price') }}</th>
                            <th class="px-6 py-4">{{ __('cart.th_quantity') }}</th>
                            <th class="px-6 py-4">{{ __('cart.th_subtotal') }}</th>
                            <th class="px-6 py-4">{{ __('cart.th_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $total = 0; @endphp
                        @foreach (session('cart') as $id => $item)
                            @php
                                $subtotal = $item['price'] * $item['quantity'];
                                $total += $subtotal;
                            @endphp
                            <tr class="border-t">
                                <td class="flex items-center px-6 py-4 space-x-4">
                                    <img src="/storage/{{ $item['image_path'] }}" class="object-cover w-12 h-12 rounded"
                                        alt="">
                                    <span>{{ $item['name'] }}</span>
                                </td>
                                <td class="px-6 py-4">{{ __('product.currency_aed') }}
                                    {{ number_format($item['price'], 2) }}</td>
                                <td class="px-6 py-4">{{ $item['quantity'] }}</td>
                                <td class="px-6 py-4">{{ __('product.currency_aed') }} {{ number_format($subtotal, 2) }}
                                </td>
                                <td class="px-6 py-4">
                                    <form method="POST" action="{{ route('cart.remove', $product->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">
                                            {{ __('cart.remove') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @php
                // Subtotal you already computed above in $total:
                $itemsSubtotal = (float) ($total ?? 0.0);

                // Read applied promos from session (array).
                $promos = collect(session('promos', []));

                // Shipping base amount (if you track it in session)
                $shippingBefore = (float) (session('shipping_amount') ?? 0.0);
                $hasFreeShipping = $promos->contains(fn($p) => ($p['discount_type'] ?? null) === 'shipping');
                $shippingAfter = $hasFreeShipping ? 0.0 : $shippingBefore;

                // We allow only ONE discount promo (fixed OR percentage)
                $discountPromo = $promos->first(
                    fn($p) => in_array($p['discount_type'] ?? '', ['fixed', 'percentage'], true),
                );

                // Compute discount amount dynamically
                $discountAmount = 0.0;
                if ($discountPromo) {
                    $type = $discountPromo['discount_type'] ?? '';
                    if ($type === 'fixed') {
                        // Prefer an explicit amount; fall back to 'value'
                        $discountAmount = (float) ($discountPromo['amount'] ?? ($discountPromo['value'] ?? 0.0));
                    } elseif ($type === 'percentage') {
                        $percent = (float) ($discountPromo['percent'] ?? ($discountPromo['value'] ?? 0.0));
                        $discountAmount = round($itemsSubtotal * ($percent / 100), 2);
                    }
                    // Never discount more than items subtotal
                    $discountAmount = min($discountAmount, $itemsSubtotal);
                }

                // Grand total = items + shipping(after promo) - discount
                $grandTotal = max(0, $itemsSubtotal + $shippingAfter - $discountAmount);

                // Helpers for UI
                $money = fn($v) => number_format((float) $v, 2);

                // For the promo list UI: show computed amount per code (only discount promo has a value)
                $promoAmountsByCode = [];
                if ($discountPromo && !empty($discountPromo['code'])) {
                    $promoAmountsByCode[$discountPromo['code']] = $discountAmount;
                }
            @endphp


            <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-12">

                {{-- Left: Promotions --}}
                <div class="md:col-span-7">
                    <div class="rounded-2xl border border-black/10 bg-white shadow-sm">
                        <div class="px-5 py-4 border-b border-black/10 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-black">Promotions</h3>
                            {{-- Small hint to use the field above --}}
                            <span class="text-xs text-gray-500">Use the promo field above</span>
                        </div>

                        @if ($promos->isNotEmpty())
                            <div class="px-5 py-4 space-y-3">
                                @foreach ($promos as $promo)
                                    @php $type = $promo['discount_type'] ?? ''; @endphp
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            @if ($type === 'shipping')
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                    Free Shipping
                                                </span>
                                            @elseif($type === 'percentage')
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                    {{ (int) ($promo['percent'] ?? 0) }}% Off
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                    Amount Off
                                                </span>
                                            @endif

                                            <span class="text-sm text-gray-700">
                                                Code: <span class="font-mono">{{ strtoupper($promo['code'] ?? '') }}</span>
                                                @php $amt = $promoAmountsByCode[$promo['code'] ?? ''] ?? 0; @endphp
                                                @if (in_array($type, ['fixed', 'percentage']) && $amt > 0)
                                                    · <span class="text-gray-500">− CAD {{ $money($amt) }}</span>
                                                @endif

                                            </span>
                                        </div>

                                        <form method="POST" action="{{ route('cart.removePromo', $promo['code']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                class="text-xs text-gray-600 hover:text-black underline underline-offset-4">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Pleasant empty state --}}
                            <div class="px-6 py-10 text-center">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
                                    <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 14l6-6m-5.5 11H6a2 2 0 01-2-2V7a2 2 0 012-2h8l4 4v2" />
                                    </svg>
                                </div>
                                <h4 class="mt-3 text-sm font-semibold text-gray-900">No promotions applied</h4>
                                <p class="mt-1 text-sm text-gray-600">
                                    Enter a promo code above to unlock free shipping or a discount.
                                </p>
                            </div>
                        @endif
                        @error('promo_code')
                            <div class="flex bg-red-100 text-red-600 text-sm py-2 px-3 rounded-md m-5">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Right: Order summary --}}
                <div class="md:col-span-5">
                    <div class="rounded-2xl border border-black/10 bg-white shadow-sm">
                        <div class="px-5 py-4 border-b border-black/10">
                            <h3 class="text-base font-semibold text-black">Order Summary</h3>
                        </div>

                        <div class="px-5 py-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Items Subtotal</span>
                                <span class="font-medium text-black">CAD {{ $money($itemsSubtotal) }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Shipping</span>
                                @if ($hasFreeShipping && $shippingBefore > 0)
                                    <span class="font-medium">
                                        <span class="line-through text-gray-400 mr-1">CAD
                                            {{ $money($shippingBefore) }}</span>
                                        <span class="text-emerald-700">Free</span>
                                    </span>
                                @else
                                    <span class="font-medium text-black">CAD {{ $money($shippingAfter) }}</span>
                                @endif
                            </div>

                            @if ($discountAmount > 0)
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Promotion</span>
                                    <span class="font-medium text-emerald-700">− CAD {{ $money($discountAmount) }}</span>
                                </div>
                            @else
                                <div class="flex items-center justify-between text-gray-500">
                                    <span>No discount applied</span>
                                    <span>—</span>
                                </div>
                            @endif


                            <div class="h-px bg-black/10 my-2"></div>

                            <div class="flex items-center justify-between text-base">
                                <span class="font-semibold text-black">Grand Total</span>
                                <span class="font-semibold text-black">CAD {{ $money($grandTotal) }}</span>
                            </div>
                        </div>

                        <div class="px-5 pt-0 pb-5">
                            <a href="{{ route('checkout.index') }}"
                                class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl
                  bg-gradient-to-r from-black via-neutral-700 to-black text-white font-medium
                  bg-[length:200%_100%] bg-left hover:bg-right transition-all duration-500">
                                Proceed to Checkout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-gray-600 text-center">
                {{ __('cart.empty') }}
            </div>
        @endif
    </div>
@endsection
