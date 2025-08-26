@extends('layouts.app')

@section('content')
<div class="max-w-4xl px-4 py-10 mx-auto sm:px-6 lg:px-8 min-h-screen bg-cream"
     x-data="checkoutState({
        // Server data
        countries: {{ Js::from($countries) }},                // { country: [city, ...] }
        shippingOptions: {{ Js::from($shippingOptions) }},    // [{ id, name, price, country, cities: [...] }, ...]
        currency: '{{ __('product.currency_aed') }}',
        baseTotal: {{ number_format($subtotal - ($discount ?? 0), 2, '.', '') }},

        // Initial selections (prefer old() if present)
        initialCountry: '{{ old('country') ?: (array_key_first($countries) ?? '') }}',
        initialCity: '{{ old('city') }}',
        initialSelectedId: '{{ old('shipping_option_id') }}',
     })">

    {{-- Cart Items --}}
    <div class="p-6 mb-6 bg-white rounded-lg shadow border border-cream/60">
        <h2 class="mb-4 text-xl font-semibold text-primary">{{ __('checkout.your_cart') }}</h2>

        @if (count($cart) > 0)
            <ul class="divide-y divide-cream/60">
                @foreach ($cart as $id => $item)
                    <li class="flex items-start justify-between py-3">
                        <div class="pr-4">
                            <p class="font-medium text-charcoal">{{ $item['name'] }}</p>
                            <p class="text-sm text-charcoal/70">{{ __('checkout.qty') }}: {{ $item['quantity'] }}</p>
                        </div>
                        <div class="text-charcoal">
                            {{ __('product.currency_aed') }} {{ number_format($item['price'] * $item['quantity'], 2) }}
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
        @endif

        {{-- Country / City --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.country') }}</label>
                <select x-model="country" name="country" required
                        class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                               focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    <template x-for="(list, c) in countries" :key="c">
                        <option :value="c" x-text="c"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.city') }}</label>
                <select x-model="city" name="city" required
                        class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                               focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    <template x-for="ct in cities()" :key="ct">
                        <option :value="ct" x-text="ct"></option>
                    </template>
                </select>
            </div>
        </div>

        {{-- Shipping Option --}}
        <div>
            <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.shipping_option') }}</label>
            <select x-model="selectedShippingId" name="shipping_option_id" required
                    class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                           focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                <option value="" disabled>{{ __('checkout.select_shipping') }}</option>
                <template x-for="opt in matchingShippingOptions()" :key="opt.id">
                    <option :value="opt.id"
                            x-text="formatOption(opt)">
                    </option>
                </template>
            </select>
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
        <div class="p-4 mt-4 bg-cream border border-primary/10 rounded">
            <p class="mb-2 text-charcoal">
                {{ __('checkout.subtotal') }}: {{ __('product.currency_aed') }} {{ number_format($subtotal, 2) }}
            </p>

            @if ($coupon)
                <p class="mb-2 text-green-600">
                    {{ __('checkout.coupon_discount') }}: -{{ __('product.currency_aed') }} {{ number_format($discount, 2) }}
                </p>
            @endif

            <p class="mb-2 text-charcoal">
                {{ __('checkout.shipping') }}:
                <span x-text="currency + ' ' + shippingCost.toFixed(2)"></span>
            </p>

            <p class="font-bold text-gold tracking-wide">
                {{ __('checkout.total') }}:
                <span x-text="currency + ' ' + (baseTotal + shippingCost).toFixed(2)"></span>
            </p>
        </div>

        <input type="hidden" name="shipping_cost" :value="shippingCost.toFixed(2)">

        <button type="submit"
                :disabled="!selectedShippingId"
                class="w-full py-3 text-lg font-semibold text-white bg-primary rounded
                       hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-gold/40
                       disabled:opacity-50 disabled:cursor-not-allowed">
            {{ __('checkout.confirm_pay') }}
        </button>
    </form>
</div>

{{-- Alpine: state + helpers --}}
<script>
function checkoutState(cfg) {
  return {
    // Server/state
    countries: cfg.countries || {},
    shippingOptionsRaw: Array.isArray(cfg.shippingOptions) ? cfg.shippingOptions : [],
    currency: cfg.currency || 'AED',
    baseTotal: Number(cfg.baseTotal || 0),

    // Selection
    country: cfg.initialCountry || '',
    city: cfg.initialCity || '',
    selectedShippingId: cfg.initialSelectedId || '',
    shippingCost: 0,

    // Derived / normalized
    get shippingOptions() {
      // Normalize each option's cities to array of strings
      return this.shippingOptionsRaw.map(o => ({
        ...o,
        _cities: Array.isArray(o.cities)
          ? o.cities.map(c => typeof c === 'string' ? c : (c?.city ?? c)).filter(Boolean)
          : []
      }));
    },

    // UI helpers
    cities() {
      return this.countries?.[this.country] || [];
    },
    includesCity(opt, city) {
      return !!opt?._cities?.includes(city);
    },
    matchingShippingOptions() {
      // Filter by chosen country + whether the chosen city is supported
      const list = this.shippingOptions.filter(opt =>
        opt.country === this.country && this.includesCity(opt, this.city)
      );
      // Sort by price (cheapest first)
      return list.sort((a, b) => Number(a.price) - Number(b.price));
    },
    resolveFirstCity() {
      // Ensure current city belongs to chosen country; otherwise pick first
      const list = this.cities();
      if (!list.length) { this.city = ''; return; }
      if (!list.includes(this.city)) this.city = list[0];
    },
    resolveShippingSelection() {
      const matches = this.matchingShippingOptions();
      if (!matches.length) {
        this.selectedShippingId = '';
        this.shippingCost = 0;
        return;
      }
      // If current selected id is not in matches, pick the cheapest
      const stillValid = matches.find(m => String(m.id) === String(this.selectedShippingId));
      const chosen = stillValid || matches[0];
      this.selectedShippingId = chosen.id;
      this.shippingCost = Number(chosen.price || 0);
    },
    calculateShipping() {
      const chosen = this.shippingOptions.find(opt => String(opt.id) === String(this.selectedShippingId));
      this.shippingCost = chosen ? Number(chosen.price || 0) : 0;
    },
    formatOption(opt) {
      const price = Number(opt.price || 0).toFixed(2);
      const dt = (opt.delivery_time ?? '').toString().trim();
      return dt ? `${opt.name} — ${this.currency} ${price} (${dt})`
                : `${opt.name} — ${this.currency} ${price}`;
    },

    // Lifecycle
    init() {
      // Ensure country has a value
      if (!this.country) {
        const keys = Object.keys(this.countries || {});
        this.country = keys.length ? keys[0] : '';
      }
      this.resolveFirstCity();
      this.resolveShippingSelection();

      // Reactivity
      this.$watch('country', () => {
        this.resolveFirstCity();
        this.resolveShippingSelection();
      });
      this.$watch('city', () => this.resolveShippingSelection());
      this.$watch('selectedShippingId', () => this.calculateShipping());
    }
  }
}
</script>
@endsection
