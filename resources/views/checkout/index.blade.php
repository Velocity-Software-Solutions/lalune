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
        $shippingBefore = (float) (session('shipping_amount') ?? 15.0);

        // Shipping promo?
        $hasFreeShipping = $promos->contains(fn($p) => ($p['discount_type'] ?? null) === 'shipping');

        // One discount promo (fixed or percentage)
        $discountPromo = $promos->first(fn($p) => in_array($p['discount_type'] ?? '', ['fixed', 'percentage'], true));

        // Compute discount amount
        $discountAmount = 0.0;
        if ($discountPromo) {
            $type = $discountPromo['discount_type'] ?? '';
            if ($type === 'fixed') {
                $discountAmount = (float) ($discountPromo['amount'] ?? ($discountPromo['value'] ?? 0.0));
            } elseif ($type === 'percentage') {
                $percent = (float) ($discountPromo['percent'] ?? ($discountPromo['value'] ?? 0.0));
                $discountAmount = round($itemsSubtotal * ($percent / 100), 2);
            }
            $discountAmount = min($discountAmount, $itemsSubtotal);
        }

        // Effective items total after discount
        $baseTotalAfterDiscount = max(0, $itemsSubtotal - $discountAmount);

        // Shipping (PHP display only; actual shipping submitted is from Alpine hidden inputs)
        $shippingAfter = $hasFreeShipping ? 0.0 : $shippingBefore;

        // ✅ Apply 13% tax on items subtotal after discount (before shipping)
        $taxRate = 0.13;
        $taxAmount = round($baseTotalAfterDiscount * $taxRate, 2);

        // ✅ Final grand total (PHP display only; actual total can differ if user picks different shipping)
        $grandTotal = $baseTotalAfterDiscount + $taxAmount + $shippingAfter;
    @endphp

    <div class="max-w-5xl px-4 py-10 mx-auto sm:px-6 lg:px-8 min-h-screen bg-white" x-data="checkoutState({
        countries: {{ Js::from($countries) }},
        shippingOptions: {{ Js::from(
            collect($shippingOptions ?? [])->map(
                    fn($o) => [
                        'id' => (string) ($o->id ?? ($o['id'] ?? '')),
                        'name' => (string) ($o->name ?? ($o['name'] ?? 'Shipping')),
                        'price' => (float) ($o->price ?? ($o['price'] ?? 0)),
                        'country' => (string) ($o->country ?? ($o['country'] ?? '')),
                        'cities' => collect($o->cities ?? ($o['cities'] ?? []))->map(fn($c) => is_array($c) ? $c['city'] ?? '' : (is_object($c) ? $c->city ?? '' : (string) $c))->filter()->values()->all(),
                        'delivery_time' => (string) ($o->delivery_time ?? ($o['delivery_time'] ?? '')),
                    ],
                )->values(),
        ) }},
        currency: 'CAD',
        defaultShippingPrice: 15,
    
        baseTotal: {{ Js::from($baseTotalAfterDiscount) }},
    
        shippingBefore: {{ Js::from($shippingBefore) }},
        hasFreeShipping: {{ Js::from($hasFreeShipping) }},
    
        initialCountry: '{{ old('country') ?: array_key_first($countries) ?? '' }}',
        initialState: '{{ old('state') }}',
        initialShippingId: '{{ old('shipping_option_id') }}',
    })">

        {{-- Cart Items --}}
        <div class="p-6 mb-6 bg-white rounded-lg shadow border border-white/60">
            <h2 class="mb-4 text-xl font-semibold text-black">{{ __('checkout.your_cart') }}</h2>

            @php
                $cartArray = $cart ?? session('cart', []);
                $colorIds = collect($cartArray)->pluck('color_id')->filter()->unique()->values();
                $sizeIds = collect($cartArray)->pluck('size_id')->filter()->unique()->values();

                $colorMeta = \App\Models\ProductColor::query()
                    ->whereIn('id', $colorIds)
                    ->get(['id', 'name', 'color_code'])
                    ->keyBy('id');

                $sizeMeta = \App\Models\ProductSize::query()->whereIn('id', $sizeIds)->pluck('size', 'id');
            @endphp

            @if (count($cartArray) > 0)
                <ul class="divide-y divide-white/60">
                    @foreach ($cartArray as $key => $item)
                        @php
                            $cid = $item['color_id'] ?? null;
                            $swatchHex = null;
                            $colorName = null;
                            if ($cid && $colorMeta->has($cid)) {
                                $c = $colorMeta[$cid];
                                $swatchHex = $c->color_code ? strtoupper($c->color_code) : null;
                                $colorName = $c->name ?: $swatchHex;
                            } else {
                                $hex = $item['color'] ?? null;
                                if ($hex && !str_starts_with($hex, '#') && preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
                                    $hex = '#' . $hex;
                                }
                                $swatchHex = $hex;
                                $colorName = $hex;
                            }

                            $sid = $item['size_id'] ?? null;
                            $sizeLabel =
                                $sid && $sizeMeta->has($sid) ? (string) $sizeMeta[$sid] : $item['size'] ?? null;

                            $lineTotal = (float) ($item['price'] * $item['quantity']);
                        @endphp

                        <li class="flex items-start justify-between py-3">
                            <div class="flex items-start gap-3 pr-4">
                                @if (!empty($item['image_path']))
                                    <img src="{{ asset('storage/' . $item['image_path']) }}"
                                        class="w-12 h-12 rounded object-cover" alt="{{ $item['name'] }}">
                                @endif

                                <div>
                                    <p class="font-medium text-charcoal">{{ $item['name'] }}</p>

                                    <div class="mt-1 flex items-center gap-4 text-xs text-gray-600">
                                        @if ($swatchHex)
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="inline-block w-3.5 h-3.5 rounded-full border"
                                                    style="background: {{ $swatchHex }}"
                                                    title="{{ $swatchHex }}"></span>
                                                <span>Color: <span class="font-medium">{{ $colorName }}</span></span>
                                            </span>
                                        @endif

                                        @if ($sizeLabel)
                                            <span class="inline-flex items-center gap-1.5">
                                                <span>Size:</span>
                                                <span
                                                    class="px-2 py-0.5 rounded border border-gray-300 text-gray-800 bg-gray-50">
                                                    {{ strtoupper($sizeLabel) }}
                                                </span>
                                            </span>
                                        @endif

                                        @if (empty($swatchHex) && empty($sizeLabel))
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-charcoal/70">
                                        {{ __('checkout.qty') }}: {{ $item['quantity'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="text-charcoal">
                                CAD {{ number_format($lineTotal, 2) }}
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

        {{-- Promo Codes --}}
        <div class="p-6 mb-6 bg-white rounded-lg shadow border border-white/60">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-black">Promo Codes</h2>
                <span class="text-xs text-gray-500">Free shipping or discounts</span>
            </div>

            <form action="{{ route('cart.applyPromo') }}" method="POST" class="mt-4 flex flex-col sm:flex-row gap-3">
                @csrf
                <div class="flex-1">
                    <label for="promo_code" class="block text-sm font-medium text-charcoal">Have a promo?</label>
                    <input id="promo_code" type="text" name="promo_code" value="{{ old('promo_code') }}"
                        placeholder="Enter promo code" autocomplete="off"
                        class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                                  focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    @error('promo_code')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:pt-6">
                    <button type="submit"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-md text-white bg-gray-700
                                   hover:bg-gray-700/90 focus:outline-none focus:ring-2 focus:ring-black/40 transition">
                        Apply
                    </button>
                </div>
            </form>

            @php $promos = collect(session('promos', [])); @endphp

            @if ($promos->isNotEmpty())
                <div class="mt-5 space-y-3">
                    <p class="text-sm text-charcoal/70">Applied promotions:</p>

                    @foreach ($promos as $promo)
                        @php
                            $type = $promo['discount_type'] ?? '';
                            $code = strtoupper($promo['code'] ?? '');
                        @endphp

                        <div
                            class="flex items-center justify-between gap-3 p-3 rounded-lg border border-primary/10 bg-white">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if ($type === 'shipping')
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Free Shipping
                                    </span>
                                @elseif($type === 'percentage')
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                        {{ (int) ($promo['percent'] ?? ($promo['value'] ?? 0)) }}% Off
                                    </span>
                                @elseif($type === 'fixed')
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                        Amount Off
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        Promo
                                    </span>
                                @endif

                                <span class="text-sm text-gray-700">
                                    Code: <span class="font-mono font-semibold">{{ $code }}</span>
                                </span>
                            </div>

                            <form method="POST" action="{{ route('cart.removePromo', $promo['code']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-xs text-gray-600 hover:text-black underline underline-offset-4">
                                    Remove
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 text-sm text-charcoal/70">No promo applied yet. Enter a code above.</div>
            @endif
        </div>

        {{-- Checkout Form --}}
        <form method="POST" action="{{ route('checkout.process') }}" class="space-y-4">
            @csrf

            {{-- Guest fields --}}
            <div>
                <label for="full_name"
                    class="block text-sm font-medium text-charcoal">{{ __('checkout.full_name') }}</label>
                <input type="text" name="full_name" id="full_name" required
                    class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    placeholder="{{ __('checkout.full_name_placeholder') }}">
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-charcoal">Phone Number</label>
                <input type="text" name="phone" id="phone" required
                    class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    placeholder="+1 (000) 000-0000">
            </div>

            <div>
                <label for="email"
                    class="block text-sm font-medium text-charcoal">{{ __('checkout.email_address') }}</label>
                <input type="email" name="email" id="email" required
                    class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                              focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    placeholder="{{ __('checkout.email_placeholder') }}">
            </div>

            {{-- Country / State / City --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.country') }}</label>
                    <select x-model="country" name="country" required @change="onLocationChange(true)"
                        class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <template x-for="(states, c) in countries" :key="c">
                            <option :value="c" x-text="c"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-medium text-charcoal">{{ __('checkout.state') }}</label>
                    <select x-model="state" name="state" required @change="onLocationChange()"
                        class="w-full p-2 rounded border border-primary/30 bg-white text-charcoal
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <template x-for="(name, code) in statesForCountry()" :key="code">
                            <option :value="code" x-text="name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label for="city" class="block text-sm font-medium text-charcoal">City</label>
                    <input type="text" name="city" id="city" required x-model="city"
                        class="w-full mt-1 rounded-md border border-primary/30 bg-white text-charcoal shadow-sm
                                  focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        placeholder="Enter your city">
                </div>
            </div>

            {{-- Shipping Rates (options + fallback) --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block font-medium text-charcoal">{{ __('checkout.shipping_rates') }}</label>
                    <span class="text-xs text-gray-500" x-show="resolvedOptions.length">
                        Based on your country/state
                    </span>
                </div>

                {{-- Free shipping promo note --}}
                <template x-if="hasFreeShipping">
                    <p class="text-sm text-emerald-700">
                        Free shipping promotion applied
                        <span class="text-gray-400 line-through ml-1"
                            x-text="formatMoney(selectedShippingBeforePromo)"></span>
                    </p>
                </template>

                {{-- Single option (auto selected) --}}
                <template x-if="!hasFreeShipping && resolvedOptions.length === 1">
                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900 truncate"
                                        x-text="resolvedOptions[0].name"></span>

                                    <template x-if="resolvedOptions[0]._fallback">
                                        <span
                                            class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">
                                            Default
                                        </span>
                                    </template>
                                </div>

                                <div class="mt-0.5 flex items-center gap-2 text-[12px] text-gray-500">
                                    <span x-show="resolvedOptions[0].delivery_time"
                                        x-text="`Delivery: ${resolvedOptions[0].delivery_time} days`"></span>
                                    <span
                                        x-show="resolvedOptions[0].delivery_time && resolvedOptions[0]._scopeLabel">•</span>
                                    <span class="truncate" x-text="resolvedOptions[0]._scopeLabel"></span>
                                </div>
                            </div>

                            <div class="shrink-0 text-sm font-semibold text-gray-900"
                                x-text="formatMoney(resolvedOptions[0].price)"></div>
                        </div>
                    </div>
                </template>

                {{-- Multiple options (radio) --}}
                <template x-if="!hasFreeShipping && resolvedOptions.length > 1">
                    <div class="space-y-2">
                        <template x-for="opt in resolvedOptions" :key="opt.id">
                            <label
                                class="group flex items-start gap-3 rounded-lg border px-3 py-2 cursor-pointer transition
                       hover:bg-gray-50"
                                :class="String(selectedOptionId) === String(opt.id) ?
                                    'border-black bg-gray-50' :
                                    'border-gray-200 bg-white'">

                                <input class="mt-1 h-4 w-4 accent-black" type="radio" name="shipping_option_choice"
                                    :value="opt.id" x-model="selectedOptionId">

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-gray-900 truncate"
                                                    x-text="opt.name"></span>

                                                <template x-if="opt._fallback">
                                                    <span
                                                        class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">
                                                        Default
                                                    </span>
                                                </template>
                                            </div>

                                            <div class="mt-0.5 flex items-center gap-2 text-[12px] text-gray-500">
                                                <span x-show="opt.delivery_time"
                                                    x-text="`Delivery: ${opt.delivery_time} days`"></span>
                                                <span x-show="opt.delivery_time && opt._scopeLabel">•</span>
                                                <span class="truncate" x-text="opt._scopeLabel"></span>
                                            </div>
                                        </div>

                                        <div class="shrink-0 text-sm font-semibold text-gray-900"
                                            x-text="formatMoney(opt.price)"></div>
                                    </div>
                                </div>
                            </label>
                        </template>

                        <p class="text-[12px] text-gray-500" x-show="!selectedOptionId">
                            Choose a shipping option to continue.
                        </p>
                    </div>
                </template>

                {{-- When free shipping is active, still show which option would have applied --}}
                <template x-if="hasFreeShipping">
                    <div class="text-xs text-gray-500">
                        Shipping method:
                        <span class="font-medium" x-text="selectedShipping?.name || 'Standard Shipping'"></span>
                    </div>
                </template>

                {{-- POST to backend --}}
                <input type="hidden" name="shipping_option_id" :value="selectedOptionId || (selectedShipping?.id ?? '')">
                <input type="hidden" name="shipping_cost" :value="previewShipping.toFixed(2)">
            </div>

            {{-- Addresses --}}
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

            {{-- Order Summary (PHP display) --}}
            <div class="p-4 mt-4 bg-white border border-primary/10 rounded space-y-2">
                <p class="text-charcoal">
                    {{ __('checkout.subtotal') }}: CAD {{ number_format($itemsSubtotal, 2) }}
                </p>

                @if ($discountAmount > 0)
                    <p class="text-emerald-700">
                        {{ __('checkout.coupon_discount') }}:
                        − CAD {{ number_format($discountAmount, 2) }}
                        @if (optional($discountPromo)['discount_type'] === 'percentage')
                            <span class="text-gray-500">
                                ({{ (int) ($discountPromo['percent'] ?? ($discountPromo['value'] ?? 0)) }}%)
                            </span>
                        @endif
                    </p>
                @endif

                <p class="text-charcoal">
                    Tax (13%): CAD {{ number_format($taxAmount, 2) }}
                </p>

                <p class="text-charcoal flex items-center justify-between gap-3">
                    <span>{{ __('checkout.shipping') }}:</span>

                    {{-- Free shipping --}}
                    <template x-if="hasFreeShipping">
                        <span class="text-right">
                            <span class="line-through text-gray-400"
                                x-text="formatMoney(selectedShippingBeforePromo)"></span>
                            <span class="ml-1 font-semibold text-emerald-700">Free</span>
                        </span>
                    </template>

                    {{-- Normal shipping --}}
                    <template x-if="!hasFreeShipping">
                        <span class="font-medium text-charcoal" x-text="formatMoney(previewShipping)"></span>
                    </template>
                </p>
                <p class="font-bold text-black tracking-wide">
                    {{ __('checkout.total') }}: CAD {{ number_format($grandTotal, 2) }}
                </p>
            </div>

            <button type="submit"
                class="group relative w-full overflow-hidden rounded-xl bg-black py-3 text-lg font-semibold text-white
                       focus:outline-none focus:ring-2 focus:ring-black/40
                       transition-all duration-300 ease-out
                       hover:shadow-[0_0_30px_rgba(255,255,255,0.08)]
                       active:scale-[0.98]
                       disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none">
                <span
                    class="pointer-events-none absolute inset-0 -translate-x-full
                             bg-gradient-to-r from-transparent via-white/20 to-transparent
                             transition-transform duration-1000 ease-out
                             group-hover:translate-x-full"></span>

                <span class="relative flex items-center justify-center gap-2">
                    <span
                        class="material-icons-outlined text-white/80 transition-all duration-300 group-hover:text-white group-hover:scale-110">
                        lock
                    </span>
                    <span class="tracking-wide">Secure Checkout</span>
                </span>

                <span
                    class="pointer-events-none absolute bottom-0 left-1/2 h-[2px] w-0
                             -translate-x-1/2 bg-white/50
                             transition-all duration-400 ease-out
                             group-hover:w-2/3"></span>
            </button>
        </form>
    </div>

    {{-- Alpine --}}
    <script>
        function checkoutState(cfg) {
            return {
                countries: cfg.countries || {},
                shippingOptions: Array.isArray(cfg.shippingOptions) ? cfg.shippingOptions : [],
                defaultShippingPrice: Number(cfg.defaultShippingPrice ?? 15),
                currency: cfg.currency || 'CAD',

                country: cfg.initialCountry || '',
                state: cfg.initialState || '',
                city: cfg.initialCity || '',

                hasFreeShipping: !!cfg.hasFreeShipping,
                selectedOptionId: cfg.initialShippingId ? String(cfg.initialShippingId) : null,

                // ===== Helpers =====
                statesForCountry() {
                    return this.countries?.[this.country] || {}; // code => name
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

                // ✅ cities from DB might be array, object, string -> normalize to array of strings
                normalizeList(v) {
                    if (Array.isArray(v)) return v;
                    if (v && typeof v === 'object') return Object.values(v); // keyed array / collection
                    if (typeof v === 'string') return v.split(','); // "ON,QC"
                    return [];
                },

                // ✅ state name for matching (handles code vs name mismatch)
                get selectedStateName() {
                    const map = this.statesForCountry(); // code => name
                    return (map?.[this.state] ?? this.state ?? '').toString();
                },

                // ===== Shipping resolution (country + STATE, where DB "cities" are states) =====
                _cacheKey() {
                    return `${(this.country||'').trim()}||${(this.state||'').trim()}`;
                },
                _lastKey: null,
                _lastResolved: [],

                get resolvedOptions() {
                    const key = this._cacheKey();
                    if (key === this._lastKey) return this._lastResolved;

                    const country = (this.country || '').trim();
                    const stateCodeLower = (this.state || '').trim().toLowerCase();
                    const stateNameLower = (this.selectedStateName || '').trim().toLowerCase();

                    let list = [];

                    if (country) {
                        list = this.shippingOptions
                            .filter(o => String((o.country || '')).trim() === country)
                            .map(o => {
                                // ✅ "cities" are STATES (already strings after PHP mapping)
                                const statesLower = (Array.isArray(o.cities) ? o.cities : [])
                                    .map(s => String(s).trim().toLowerCase())
                                    .filter(Boolean);

                                const isCountryWide = statesLower.length === 0;

                                // match if list contains state code OR state name
                                const matchesState = isCountryWide ? true : (
                                    (stateCodeLower && statesLower.includes(stateCodeLower)) ||
                                    (stateNameLower && statesLower.includes(stateNameLower))
                                );

                                return {
                                    ...o,
                                    id: String(o.id),
                                    name: String(o.name || 'Shipping'),
                                    price: Number(o.price || 0),
                                    delivery_time: String(o.delivery_time || ''),
                                    _fallback: false,
                                    _scopeLabel: isCountryWide ?
                                        `${country} • Country-wide` : `${country} • ${this.selectedStateName}`,
                                    _ok: matchesState,
                                };
                            })
                            .filter(o => o._ok);
                    }

                    if (!list.length) {
                        list = [{
                            id: 'fallback',
                            name: 'Standard Shipping',
                            price: this.defaultShippingPrice,
                            delivery_time: '',
                            _fallback: true,
                            _scopeLabel: (country ? `${country} • Default` : 'Default'),
                        }];
                        this.selectedOptionId = 'fallback';
                    } else {
                        if (!list.some(o => String(o.id) === String(this.selectedOptionId))) {
                            this.selectedOptionId = (list.length === 1) ? String(list[0].id) : null;
                        }
                    }

                    this._lastKey = key;
                    this._lastResolved = list;
                    return list;
                },
                get selectedShipping() {
                    const list = this.resolvedOptions;
                    if (!this.selectedOptionId && list.length === 1) return list[0];
                    return list.find(o => String(o.id) === String(this.selectedOptionId)) || list[0] || null;
                },

                get selectedShippingBeforePromo() {
                    return this.selectedShipping ? Number(this.selectedShipping.price || 0) : this.defaultShippingPrice;
                },

                get previewShipping() {
                    if (this.hasFreeShipping) return 0;
                    return this.selectedShipping ? Number(this.selectedShipping.price || 0) : this.defaultShippingPrice;
                },

                onLocationChange(resetState = false) {
                    if (resetState) this.resolveFirstState();
                    this.selectedOptionId = null;
                    this._lastKey = null;
                    void this.resolvedOptions;
                },

                init() {
                    if (!this.country) {
                        const keys = Object.keys(this.countries || {});
                        this.country = keys.length ? keys[0] : '';
                    }
                    this.resolveFirstState();
                    void this.resolvedOptions;
                }
            }
        }
    </script>
@endsection
