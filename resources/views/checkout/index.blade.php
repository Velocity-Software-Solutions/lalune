@extends('layouts.app')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
@php
    // Existing subtotal from your controller:
    $itemsSubtotal = (float) ($subtotal ?? 0);

    // Read applied promos (from session)
    $promos = collect(session('promos', []));

    // Shipping: you can keep your default (15 CAD) here or read from session
    $shippingBefore = (float) (session('shipping_amount') ?? 15.00);

    // Shipping promo?
    $hasFreeShipping = $promos->contains(fn($p) => ($p['discount_type'] ?? null) === 'shipping');

    // One discount promo (fixed or percentage)
    $discountPromo = $promos->first(fn($p) => in_array(($p['discount_type'] ?? ''), ['fixed','percentage'], true));

    // Compute discount amount
    $discountAmount = 0.0;
    if ($discountPromo) {
        $type = $discountPromo['discount_type'] ?? '';
        if ($type === 'fixed') {
            $discountAmount = (float) ($discountPromo['amount'] ?? $discountPromo['value'] ?? 0.0);
        } elseif ($type === 'percentage') {
            $percent        = (float) ($discountPromo['percent'] ?? $discountPromo['value'] ?? 0.0);
            $discountAmount = round($itemsSubtotal * ($percent / 100), 2);
        }
        // cap at subtotal
        $discountAmount = min($discountAmount, $itemsSubtotal);
    }

    // Effective shipping after promo (for display)
    $shippingAfter = $hasFreeShipping ? 0.0 : $shippingBefore;

    // Base total used by Alpine = items subtotal minus discount (shipping added separately)
    $baseTotalAfterDiscount = max(0, $itemsSubtotal - $discountAmount);
@endphp

    <div class="max-w-4xl px-4 py-10 mx-auto sm:px-6 lg:px-8 min-h-screen bg-white" x-data="checkoutState({
        countries: {{ Js::from($countries) }},
        shippingOptions: {{ Js::from($shippingOptions) }},
        currency: 'CAD', // switched to CAD
        baseTotal: {{ number_format($subtotal - ($discount ?? 0), 2, '.', '') }},
        initialCountry: '{{ old('country') ?: array_key_first($countries) ?? '' }}',
        initialState: '{{ old('state') }}',
        initialMatched: {
            cost: Number('{{ old('shipping_cost', 15) }}' || 15), // default to 15 CAD
            id: '{{ old('shipping_option_id') }}'
        }
    })">


        {{-- Cart Items --}}
        <div class="p-6 mb-6 bg-white rounded-lg shadow border border-white/60">
            <h2 class="mb-4 text-xl font-semibold text-black">{{ __('checkout.your_cart') }}</h2>

            @if (count($cart) > 0)
                <ul class="divide-y divide-white/60">
                    @foreach ($cart as $id => $item)
                        <li class="flex items-start justify-between py-3">
                            <div class="pr-4">
                                <p class="font-medium text-charcoal">{{ $item['name'] }}</p>
                                <p class="text-sm text-charcoal/70">{{ __('checkout.qty') }}: {{ $item['quantity'] }}</p>
                            </div>
                            <div class="text-charcoal">
                                CAD {{ number_format($item['price'] * $item['quantity'], 2) }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-charcoal/70">{{ __('checkout.cart_empty') }}</p>
            @endif
        </div>

        @if ($errors->any())
            <div class="p-4 mb-4 text-red-800 bg-red-100 border border-red-200 rounded">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Checkout Form --}}
        <form method="POST" action="{{ route('checkout.process') }}" class="space-y-4">
            @csrf

            {{-- Guest fields --}}
            @if (!auth()->check())
                <div>
                    <label for="full_name" class="block text-sm font-medium text-charcoal">{{ __('checkout.full_name') }}</label>
                    <input type="text" name="full_name" id="full_name" required
                        class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        placeholder="{{ __('checkout.full_name_placeholder') }}">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-charcoal">{{ __('checkout.email_address') }}</label>
                    <input type="email" name="email" id="email" required
                        class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        placeholder="{{ __('checkout.email_placeholder') }}">
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.country') }}</label>
                        <select x-model="country" name="country" required
                            class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <template x-for="(states, c) in countries" :key="c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.state') }}</label>
                        <select x-model="state" name="city" required
                            class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <template x-for="(name, code) in statesForCountry()" :key="code">
                                <option :value="code" x-text="name"></option>
                            </template>
                        </select>
                    </div>
                </div>
            @endif

            {{-- Shipping Rates (fixed for now) --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block font-medium text-charcoal">{{ __('checkout.shipping_rates') }}</label>
                </div>
                <template x-if="hasFreeShipping">
                    <p class="text-sm text-emerald-700">
                        Free shipping promotion applied (was <span class="line-through text-gray-400" x-text="formatMoney(shippingBefore)"></span>)
                    </p>
                </template>
                <template x-if="!hasFreeShipping">
                    <p class="text-sm text-charcoal/70">
                        Flat shipping rate applied automatically: <span x-text="formatMoney(shippingBefore)"></span>
                    </p>
                </template>
            </div>

            {{-- Addresses --}}
            <div>
                <label for="shipping_address" class="block mb-1 font-medium text-charcoal">{{ __('checkout.shipping_address') }}</label>
                <textarea name="shipping_address" id="shipping_address" required
                    class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                         focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('shipping_address') }}</textarea>
                @error('shipping_address')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="billing_address" class="block mb-1 font-medium text-charcoal">{{ __('checkout.billing_address_optional') }}</label>
                <textarea name="billing_address" id="billing_address"
                    class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                         focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('billing_address') }}</textarea>
            </div>

            {{-- Order Summary --}}
            <div class="p-4 mt-4 bg-white border border-primary/10 rounded space-y-2">
                <p class="text-charcoal">
                    {{ __('checkout.subtotal') }}:
                    CAD {{ number_format($itemsSubtotal, 2) }}
                </p>

                {{-- Promo discount (computed) --}}
                @if ($discountAmount > 0)
                    <p class="text-emerald-700">
                        {{ __('checkout.coupon_discount') }}:
                        − CAD {{ number_format($discountAmount, 2) }}
                        @if(optional($discountPromo)['discount_type'] === 'percentage')
                            <span class="text-gray-500">({{ (int)($discountPromo['percent'] ?? $discountPromo['value'] ?? 0) }}%)</span>
                        @endif
                    </p>
                @endif

                {{-- Shipping line with free shipping strike-through --}}
                <p class="text-charcoal">
                    {{ __('checkout.shipping') }}:
                    @if ($hasFreeShipping && $shippingBefore > 0)
                        <span class="line-through text-gray-400">CAD {{ number_format($shippingBefore, 2) }}</span>
                        <span class="ml-1 text-emerald-700">Free</span>
                    @else
                        CAD {{ number_format($shippingAfter, 2) }}
                    @endif
                </p>

                {{-- Grand total: baseTotal (after discount) + shippingAfter --}}
                <p class="font-bold text-black tracking-wide">
                    {{ __('checkout.total') }}:
                    <span
                        x-text="formatMoney(Number(baseTotal || 0) + (hasFreeShipping ? 0 : Number(shippingBefore || 0)))">
                    </span>
                </p>
            </div>

            {{-- Hidden shipping value posted to server (actual after promo) --}}
            <input type="hidden" name="shipping_cost"
                   :value="(hasFreeShipping ? 0 : Number(shippingBefore || 0)).toFixed(2)">

            <button type="submit"
                class="w-full py-3 text-lg font-semibold text-white bg-gray-700 rounded
                       hover:bg-gray-700/90 focus:outline-none focus:ring-2 focus:ring-black/40
                       disabled:opacity-50 disabled:cursor-not-allowed">
                {{ __('checkout.confirm_pay') }}
            </button>
        </form>
    </div>

    {{-- Alpine --}}
    <script>
        function checkoutState(cfg) {
            return {
                countries: cfg.countries || {},
                shippingOptions: Array.isArray(cfg.shippingOptions) ? cfg.shippingOptions : [],
                currency: cfg.currency || 'CAD',

                // Subtotal minus discount (computed in PHP)
                baseTotal: Number(cfg.baseTotal || 0),

                // Location
                country: cfg.initialCountry || '',
                state: cfg.initialState || '',

                // Shipping
                shippingBefore: Number(cfg.shippingBefore || 15), // default before promo
                hasFreeShipping: !!cfg.hasFreeShipping,

                // For UI only
                promoDiscount: Number(cfg.promoDiscount || 0),
                discountLabel: cfg.discountLabel || null,

                // helpers
                statesForCountry() {
                    return this.countries?.[this.country] || {};
                },
                formatMoney(n) {
                    return `${this.currency} ${Number(n || 0).toFixed(2)}`;
                },
                resolveFirstState() {
                    const entries = Object.entries(this.statesForCountry());
                    if (!entries.length) { this.state = ''; return; }
                    const firstCode = entries[0][0];
                    if (!Object.keys(this.statesForCountry()).includes(this.state)) {
                        this.state = firstCode;
                    }
                },

                init() {
                    if (!this.country) {
                        const keys = Object.keys(this.countries || {});
                        this.country = keys.length ? keys[0] : '';
                    }
                    this.resolveFirstState();
                }
            }
        }
    </script>
@endsection
