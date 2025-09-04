@extends('layouts.app')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
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
                                {{ __('product.currency_aed') }}
                                {{ number_format($item['price'] * $item['quantity'], 2) }}
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

            {{-- Guest fields ... --}}
            @if (!auth()->check())
                <div>
                    <label for="full_name"
                        class="block text-sm font-medium text-charcoal">{{ __('checkout.full_name') }}</label>
                    <input type="text" name="full_name" id="full_name" required
                        class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        placeholder="{{ __('checkout.full_name_placeholder') }}">
                </div>

                <div>
                    <label for="email"
                        class="block text-sm font-medium text-charcoal">{{ __('checkout.email_address') }}</label>
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
                        <select x-model="state" name="state" required
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
                        {{-- <button type="button" @click="matchBestRate" ...>Match Best Rate</button> --}}
                        {{-- <span x-show="loadingRate" class="spinner"></span> --}}
                    </div>

                    {{-- <template x-if="filteredRates().length"> ... full list ... </template> --}}
                    <p class="text-sm text-charcoal/70">
                        Flat shipping rate applied automatically: CAD 15
                    </p>

                    {{-- <p class="text-xs text-charcoal/60" x-show="rateError" x-text="rateError"></p> --}}
                    {{-- <p class="text-xs text-green-700" x-show="rateOK">{{ __('checkout.rate_matched_success') }}</p> --}}
                </div>

                {{-- Addresses ... --}}
                <div>
                    <label for="shipping_address"
                        class="block mb-1 font-medium text-charcoal">{{ __('checkout.shipping_address') }}</label>
                    <textarea name="shipping_address" id="shipping_address" required
                        class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                             focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('shipping_address') }}</textarea>
                    @error('shipping_address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="billing_address"
                        class="block mb-1 font-medium text-charcoal">{{ __('checkout.billing_address_optional') }}</label>
                    <textarea name="billing_address" id="billing_address"
                        class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                             focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('billing_address') }}</textarea>
                </div>
                {{-- Order Summary --}}
                <div class="p-4 mt-4 bg-white border border-primary/10 rounded">
                    <p class="mb-2 text-charcoal">
                        {{ __('checkout.subtotal') }}: CAD {{ number_format($subtotal, 2) }}
                    </p>

                    @if ($coupon)
                        <p class="mb-2 text-green-600">
                            {{ __('checkout.coupon_discount') }}: -CAD {{ number_format($discount, 2) }}
                        </p>
                    @endif

                    <p class="mb-2 text-charcoal">
                        {{ __('checkout.shipping') }}:
                        <span x-text="formatMoney(shippingCost)"></span>
                    </p>

                    <p class="font-bold text-black tracking-wide">
                        {{ __('checkout.total') }}:
                        <span x-text="formatMoney(baseTotal + shippingCost)"></span>
                    </p>
                </div>

                <input type="hidden" name="shipping_cost" :value="Number(shippingCost || 0).toFixed(2)">

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
                baseTotal: Number(cfg.baseTotal || 0),

                country: cfg.initialCountry || '',
                state: cfg.initialState || '',

                // Fixed shipping fee
                shippingCost: 15,
                selectedShippingId: '',

                // UI flags (kept for later)
                loadingRate: false,
                rateError: '',
                rateOK: false,
                debounceTimer: null,

                statesForCountry() {
                    return this.countries?.[this.country] || {};
                },

                formatMoney(n) {
                    return `${this.currency} ${Number(n || 0).toFixed(2)}`;
                },

                resolveFirstState() {
                    const entries = Object.entries(this.statesForCountry());
                    if (!entries.length) {
                        this.state = '';
                        return;
                    }
                    const firstCode = entries[0][0];
                    if (!Object.keys(this.statesForCountry()).includes(this.state)) {
                        this.state = firstCode;
                    }
                },

                // --- Commented fetch logic ---
                // filteredRates() {
                //     const st = this.state;
                //     return this.shippingOptions
                //         .filter(o => o.country === this.country && Array.isArray(o.cities) && o.cities.includes(st))
                //         .slice()
                //         .sort((a, b) => Number(a.price) - Number(b.price));
                // },
                // scheduleRateFetch() { ... },
                // async matchBestRate() { ... },

                init() {
                    if (!this.country) {
                        const keys = Object.keys(this.countries || {});
                        this.country = keys.length ? keys[0] : '';
                    }
                    this.resolveFirstState();

                    // --- Commented watchers ---
                    // this.$watch('country', () => { this.resolveFirstState(); this.scheduleRateFetch(); });
                    // this.$watch('state', () => { this.scheduleRateFetch(); });

                    // Initial auto-fetch disabled
                    // if (!this.shippingCost) this.scheduleRateFetch();
                }
            }
        }
    </script>
@endsection
