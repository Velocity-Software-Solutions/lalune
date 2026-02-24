@extends('layouts.admin')

@section('title', 'New Order')

@section('content')
    @php
        /**
         * Expected from controller:
         * - $countries (assoc: "Canada" => ["ON"=>"Ontario", ...])
         * - $shippingOptions (collection) with ->cities relation (cities = STATES), and tax_percentage
         * - $products (collection) eager-loaded with: colors,sizes,stock,prices
         */

        // Customer suggestions (same idea as your current page)
        $knownFromOrders = collect($orders ?? [])
            ->map(fn($o) => ['email' => $o->email, 'name' => $o->full_name, 'user_id' => $o->user_id])
            ->filter(fn($x) => !empty($x['email']))
            ->unique('email')
            ->values();

        $knownFromUsers = collect($customers ?? [])
            ->map(fn($u) => ['email' => $u->email, 'name' => $u->name, 'user_id' => $u->id])
            ->filter(fn($x) => !empty($x['email']))
            ->unique('email')
            ->values();

        $knownCustomers = $knownFromOrders->isNotEmpty() ? $knownFromOrders : $knownFromUsers;

        // Normalize shipping options for Alpine
        $shipOpts = collect($shippingOptions ?? [])
            ->map(function ($o) {
                $states = collect($o->cities ?? [])
                    ->map(function ($c) {
                        return is_array($c) ? $c['city'] ?? '' : (is_object($c) ? $c->city ?? '' : (string) $c);
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => (string) ($o->id ?? ($o['id'] ?? '')),
                    'name' => (string) ($o->name ?? ($o['name'] ?? 'Shipping')),
                    'price' => (float) ($o->price ?? ($o['price'] ?? 0)),
                    'country' => (string) ($o->country ?? ($o['country'] ?? '')),
                    'states' => $states, // DB "cities" are STATES
                    'delivery_time' => (string) ($o->delivery_time ?? ($o['delivery_time'] ?? '')),
                    'tax_percentage' => $o->tax_percentage ?? ($o['tax_percentage'] ?? null),
                ];
            })
            ->values();

        // ✅ Variant-aware product payload (colors/sizes/stock/prices)
        $productsPayload = collect($products ?? [])
            ->map(function ($p) {
                $baseDiscount =
                    $p->discount_price !== null && (float) $p->discount_price > 0 ? (float) $p->discount_price : null;

                $colors = collect($p->colors ?? [])
                    ->map(
                        fn($c) => [
                            'id' => (int) $c->id,
                            'name' => (string) ($c->name ?? ''),
                            'hex' => strtoupper((string) ($c->color_code ?? '')),
                        ],
                    )
                    ->values()
                    ->all();

                $sizes = collect($p->sizes ?? [])
                    ->map(
                        fn($s) => [
                            'id' => (int) $s->id,
                            'size' => (string) ($s->size ?? ''),
                        ],
                    )
                    ->values()
                    ->all();

                $stock = collect($p->stock ?? [])
                    ->map(
                        fn($r) => [
                            'id' => (int) $r->id,
                            'colorId' => $r->color_id ? (int) $r->color_id : null,
                            'sizeId' => $r->size_id ? (int) $r->size_id : null,
                            'qty' => (int) ($r->available_qty ?? ($r->quantity_on_hand ?? 0)),
                        ],
                    )
                    ->values()
                    ->all();

                $prices = collect($p->prices ?? [])
                    ->map(
                        fn($row) => [
                            'colorId' => $row->color_id ? (int) $row->color_id : null,
                            'sizeId' => $row->size_id ? (int) $row->size_id : null,
                            'price' => is_null($row->price) ? null : (float) $row->price,
                            'discounted_price' =>
                                !is_null($row->discounted_price) && (float) $row->discounted_price > 0
                                    ? (float) $row->discounted_price
                                    : null,
                        ],
                    )
                    ->values()
                    ->all();

                return [
                    'id' => (int) $p->id,
                    'name' => (string) $p->name,
                    'status' => (int) ($p->status ?? 1),

                    'basePrice' => (float) ($p->price ?? 0),
                    'baseDiscount' => $baseDiscount,

                    'colors' => $colors,
                    'sizes' => $sizes,
                    'stock' => $stock,
                    'prices' => $prices,
                ];
            })
            ->values();
    @endphp

    <div x-data="adminOrderCreate({
        countries: {{ Js::from($countries ?? []) }},
        shippingOptions: {{ Js::from($shipOpts) }},
        products: {{ Js::from($productsPayload) }},
        currency: 'CAD',
        defaultTaxRate: 0.13,
        defaultShippingPrice: 15,
    
        old: {{ Js::from([
            'customer_mode' => old('customer_mode', 'existing'),
            'email' => old('email'),
            'user_id' => old('user_id'),
            'name' => old('name'),
            'phone' => old('phone'),
            'country' => old('country'),
            'state' => old('state'),
            'state_name' => old('state_name'),
            'city' => old('city'),
            'shipping_address' => old('shipping_address'),
            'billing_address' => old('billing_address'),
            'payment_method' => old('payment_method', 'manual'),
            'notes' => old('notes'),
            'shipping_option_id' => old('shipping_option_id'),
        ]) }}
    })"
        class="h-full max-h-full p-6 mx-3 overflow-auto bg-white rounded-2xl shadow-md dark:bg-gray-800 custom-scroll">

        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Create Order</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300 mt-1">Manual order creation with live totals, shipping &
                    tax.</p>
            </div>
            <a href="{{ route('admin.orders.index') }}"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                Back
            </a>
        </div>

        @if ($errors->any())
            <div
                class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800">
                <div class="font-semibold">There were problems with your input:</div>
                <ul class="list-disc ml-5 mt-2 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.orders.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            @csrf

            {{-- LEFT COLUMN --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- Customer --}}
                <div
                    class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Customer</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-300">Existing email or new customer</span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="customer_mode" value="existing" x-model="customerMode"
                                class="form-radio">
                            <span class="text-gray-700 dark:text-gray-200">Use previous email</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="customer_mode" value="new" x-model="customerMode"
                                class="form-radio">
                            <span class="text-gray-700 dark:text-gray-200">Enter new customer</span>
                        </label>
                    </div>

                    {{-- Existing --}}
                    <div x-show="customerMode==='existing'" x-cloak class="mt-4 space-y-3">
                        <label class="block text-xs text-gray-600 dark:text-gray-300">Customer email</label>
                        <select class="form-select w-full rounded-lg"
                            x-on:change="
                                existingEmail = $event.target.value;
                                existingUserId = $event.target.selectedOptions[0]?.dataset.userId || '';
                            ">
                            <option value="" disabled selected>Select customer email…</option>
                            @foreach ($knownCustomers as $c)
                                <option value="{{ $c['email'] }}" data-user-id="{{ $c['user_id'] ?? '' }}"
                                    @selected(old('email') === $c['email'])>
                                    {{ $c['email'] }} {{ $c['name'] ? '— ' . $c['name'] : '' }}
                                </option>
                            @endforeach
                        </select>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-300">Full name</label>
                                <input type="text" name="full_name" x-model="fullName"
                                    class="form-input w-full rounded-lg" placeholder="Customer full name" required>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-300">Phone</label>
                                <input type="text" name="phone" x-model="phone" class="form-input w-full rounded-lg"
                                    placeholder="+1 000 000 0000" required>
                            </div>
                        </div>

                        <input type="hidden" name="email" x-model="existingEmail">
                        <input type="hidden" name="user_id" x-model="existingUserId">
                    </div>

                    {{-- New --}}
                    <div x-show="customerMode==='new'" x-cloak class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300">Full name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="full_name" x-model="fullName" class="form-input w-full rounded-lg"
                                placeholder="Customer full name" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300">Email <span
                                    class="text-red-500">*</span></label>
                            <input type="email" name="email" x-model="newEmail" class="form-input w-full rounded-lg"
                                placeholder="customer@example.com" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300">Phone <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="phone" x-model="phone" class="form-input w-full rounded-lg"
                                placeholder="+1 000 000 0000" required>
                        </div>
                        <input type="hidden" name="user_id" value="">
                    </div>
                </div>

                {{-- Shipping / Billing --}}
                <div
                    class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Shipping & Billing</h3>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Country <span
                                    class="text-red-500">*</span></label>
                            <select name="country" x-model="country" @change="onLocationChange(true)"
                                class="form-select w-full rounded-lg" required>
                                <template x-for="(states, c) in countries" :key="c">
                                    <option :value="c" x-text="c"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">State / Province <span
                                    class="text-red-500">*</span></label>
                            <select name="state" x-model="state" @change="onLocationChange(false)"
                                class="form-select w-full rounded-lg" required>
                                <template x-for="(name, code) in statesForCountry()" :key="code">
                                    <option :value="code" x-text="name"></option>
                                </template>
                            </select>
                            <input type="hidden" name="state_name" :value="selectedStateName">
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">City <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="city" x-model="city" class="form-input w-full rounded-lg"
                                required>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Payment Method <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="payment_method" x-model="paymentMethod"
                                class="form-input w-full rounded-lg" required>
                            <p class="text-[12px] text-gray-500 dark:text-gray-300 mt-1">Example: manual, cash,
                                bank_transfer.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Shipping address <span
                                class="text-red-500">*</span></label>
                        <textarea name="shipping_address" x-model="shippingAddress" rows="3" class="form-textarea w-full rounded-lg"
                            required></textarea>
                    </div>

                    <div class="mt-4" x-data="{ same: true }">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" x-model="same" @change="billingSameAsShipping = same"
                                class="form-checkbox">
                            Billing same as shipping
                        </label>

                        <div class="mt-2">
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Billing address</label>
                            <textarea name="billing_address" x-model="billingAddress" rows="3" class="form-textarea w-full rounded-lg"
                                :readonly="billingSameAsShipping" :class="billingSameAsShipping ? 'opacity-60' : ''"></textarea>
                        </div>
                    </div>

                    {{-- Shipping options --}}
                    <div class="mt-5">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-800 dark:text-white">Shipping Option</label>
                            <span class="text-xs text-gray-500 dark:text-gray-300" x-text="shippingHint"></span>
                        </div>

                        <div class="mt-2 space-y-2">
                            <template x-for="opt in resolvedShippingOptions" :key="opt.id">
                                <label
                                    class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                    :class="String(selectedShippingId) === String(opt.id) ? 'border-black dark:border-white' :
                                        'border-gray-200 dark:border-gray-700'">
                                    <input type="radio" class="mt-1 h-4 w-4 accent-black" name="shipping_option_id"
                                        :value="opt.id" x-model="selectedShippingId">

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-900 dark:text-white truncate"
                                                    x-text="opt.name"></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-300 mt-0.5">
                                                    <span x-show="opt.delivery_time"
                                                        x-text="`Delivery: ${opt.delivery_time} days`"></span>
                                                    <span x-show="opt.delivery_time && opt._scopeLabel"> • </span>
                                                    <span x-text="opt._scopeLabel"></span>
                                                </div>
                                                <div class="text-[12px] text-gray-500 dark:text-gray-300 mt-1">
                                                    Tax: <span class="font-semibold"
                                                        x-text="`${Math.round(opt._taxRate*100)}%`"></span>
                                                </div>
                                            </div>

                                            <div class="shrink-0 font-semibold text-gray-900 dark:text-white"
                                                x-text="formatMoney(opt.price)"></div>
                                        </div>
                                    </div>
                                </label>
                            </template>
                        </div>

                        <p class="text-[12px] text-gray-500 dark:text-gray-300 mt-2"
                            x-show="resolvedShippingOptions.length === 0">
                            No shipping options found for this country/state. A default will be used server-side.
                        </p>

                        <input type="hidden" name="shipping_cost" :value="previewShipping.toFixed(2)">
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-800 dark:text-white">Notes</label>
                        <textarea name="notes" x-model="notes" rows="2" class="form-textarea w-full rounded-lg"></textarea>
                    </div>
                </div>

                {{-- ✅ Items (variant-aware) --}}
                <div
                    class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Items</h3>
                        <button type="button" @click="addRow()"
                            class="inline-flex items-center px-3 py-2 rounded-lg bg-black text-white hover:bg-black/90">
                            + Add item
                        </button>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-300">
                                    <th class="py-2 pr-3">Product</th>
                                    <th class="py-2 pr-3 w-44">Color</th>
                                    <th class="py-2 pr-3 w-36">Size</th>
                                    <th class="py-2 pr-3 w-24">Qty</th>
                                    <th class="py-2 pr-3 w-32">Price</th>
                                    <th class="py-2 pr-3 w-32">Line</th>
                                    <th class="py-2 w-12"></th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <template x-for="(row, i) in rows" :key="row.uid">
                                    <tr class="align-top">
                                        {{-- Product --}}
                                        <td class="py-3 pr-3">
                                            <select class="form-select w-full rounded-lg" x-model.number="row.product_id"
                                                @change="onProductChange(i, $event)">
                                                <option value="" disabled>Select product…</option>
                                                <template x-for="p in activeProducts" :key="p.id">
                                                    <option :value="p.id" x-text="p.name"></option>
                                                </template>
                                            </select>

                                            <div class="text-[12px] text-gray-500 dark:text-gray-300 mt-1"
                                                x-show="row.product_name" x-text="row.product_name"></div>

                                            {{-- submit --}}
                                            <input type="hidden" :name="`items[${i}][product_id]`"
                                                :value="row.product_id || ''">
                                            <input type="hidden" :name="`items[${i}][name]`"
                                                :value="row.product_name || ''">
                                            <input type="hidden" :name="`items[${i}][product_stock_id]`"
                                                :value="row.product_stock_id ?? ''">
                                            <input type="hidden" :name="`items[${i}][color_id]`"
                                                :value="row.color_id ?? ''">
                                            <input type="hidden" :name="`items[${i}][size_id]`"
                                                :value="row.size_id ?? ''">
                                        </td>

                                        {{-- Color --}}
                                        <td class="py-3 pr-3">
                                            <template x-if="rowHasColors(i)">
                                                <select class="form-select w-full rounded-lg"
                                                    x-model.number="row.color_id" @change="onVariantChange(i)">
                                                    <option value="" disabled>Select color…</option>
                                                    <template x-for="c in rowColors(i)" :key="c.id">
                                                        <option :value="c.id" x-text="c.name"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!rowHasColors(i)">
                                                <div class="text-gray-400 text-xs">—</div>
                                            </template>
                                        </td>

                                        {{-- Size --}}
                                        <td class="py-3 pr-3">
                                            <template x-if="rowHasSizes(i)">
                                                <select class="form-select w-full rounded-lg" x-model.number="row.size_id"
                                                    @change="onVariantChange(i)">
                                                    <option value="" disabled>Select size…</option>
                                                    <template x-for="s in rowSizes(i)" :key="s.id">
                                                        <option :value="s.id" x-text="s.size"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!rowHasSizes(i)">
                                                <div class="text-gray-400 text-xs">—</div>
                                            </template>
                                        </td>

                                        {{-- Qty --}}
                                        <td class="py-3 pr-3">
                                            <input type="number" min="1" class="form-input w-full rounded-lg"
                                                x-model.number="row.quantity" @input="recalc()"
                                                :name="`items[${i}][quantity]`">
                                            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-300"
                                                x-show="rowMaxQty(i) !== null"
                                                x-text="rowMaxQty(i) === 0 ? 'Out of stock' : `Max: ${rowMaxQty(i)}`">
                                            </div>
                                        </td>

                                        {{-- Price --}}
                                        <td class="py-3 pr-3">
                                            <input type="number" step="0.01" min="0"
                                                class="form-input w-full rounded-lg" x-model.number="row.price"
                                                @input="recalc()" :name="`items[${i}][price]`">
                                            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-300"
                                                x-show="rowVariantHint(i)" x-text="rowVariantHint(i)"></div>
                                        </td>

                                        {{-- Line --}}
                                        <td class="py-3 pr-3 font-semibold text-gray-900 dark:text-white"
                                            x-text="formatMoney((Number(row.price||0) * Number(row.quantity||1)))">
                                        </td>

                                        <td class="py-3 text-right">
                                            <button type="button" @click="removeRow(i)"
                                                class="text-red-600 hover:text-red-700" title="Remove">
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-[12px] text-gray-500 dark:text-gray-300 mt-3">
                        Price auto-fills from variant price matrix when available (discounted_price wins). You can still
                        override manually.
                    </p>
                </div>
            </div>

            {{-- RIGHT COLUMN --}}
            <div class="lg:col-span-4 space-y-6">
                <div
                    class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm sticky top-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Order Summary</h3>

                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-200">
                            <span>Items subtotal</span>
                            <span class="font-semibold" x-text="formatMoney(itemsSubtotal)"></span>
                        </div>

                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-200">
                            <span>
                                Tax
                                <span class="text-gray-500 dark:text-gray-300"
                                    x-text="`(${Math.round(effectiveTaxRate*100)}%)`"></span>
                            </span>
                            <span class="font-semibold" x-text="formatMoney(previewTax)"></span>
                        </div>

                        <div class="flex items-center justify-between text-gray-700 dark:text-gray-200">
                            <span>Shipping</span>
                            <span class="font-semibold" x-text="formatMoney(previewShipping)"></span>
                        </div>

                        <div class="h-px bg-gray-100 dark:bg-gray-700 my-2"></div>

                        <div class="flex items-center justify-between text-gray-900 dark:text-white">
                            <span class="font-semibold">Total</span>
                            <span class="text-lg font-bold" x-text="formatMoney(grandTotal)"></span>
                        </div>
                    </div>

                    <button type="submit"
                        class="mt-5 w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
                        Create Order
                    </button>

                    <a href="{{ route('admin.orders.index') }}"
                        class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 rounded-xl bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </a>

                    <div class="mt-4 text-[12px] text-gray-500 dark:text-gray-300">
                        Tip: tax rate comes from the selected shipping option’s <code>tax_percentage</code> when available.
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function adminOrderCreate(cfg) {
            const safeNumber = (v, d = 0) => {
                const n = Number(v);
                return Number.isFinite(n) ? n : d;
            };

            return {
                // data
                countries: cfg.countries || {},
                shippingOptions: Array.isArray(cfg.shippingOptions) ? cfg.shippingOptions : [],
                products: Array.isArray(cfg.products) ? cfg.products : [],
                currency: cfg.currency || 'CAD',
                defaultTaxRate: safeNumber(cfg.defaultTaxRate, 0.13),
                defaultShippingPrice: safeNumber(cfg.defaultShippingPrice, 15),

                // customer
                customerMode: (cfg.old?.customer_mode || 'existing'),
                existingEmail: (cfg.old?.email || ''),
                existingUserId: (cfg.old?.user_id || ''),
                newEmail: (cfg.old?.email || ''),
                fullName: (cfg.old?.name || cfg.old?.full_name || ''),
                phone: (cfg.old?.phone || ''),

                // location
                country: (cfg.old?.country || Object.keys(cfg.countries || {})[0] || ''),
                state: (cfg.old?.state || ''),
                city: (cfg.old?.city || ''),
                billingSameAsShipping: !(cfg.old?.billing_address),
                shippingAddress: (cfg.old?.shipping_address || ''),
                billingAddress: (cfg.old?.billing_address || ''),
                paymentMethod: (cfg.old?.payment_method || 'manual'),
                notes: (cfg.old?.notes || ''),

                // shipping selection
                selectedShippingId: (cfg.old?.shipping_option_id ? String(cfg.old.shipping_option_id) : null),

                // items
                rows: [newRow()],
                itemsSubtotal: 0,

                // helpers
                statesForCountry() {
                    return this.countries?.[this.country] || {};
                },
                get selectedStateName() {
                    const map = this.statesForCountry();
                    return (map?.[this.state] ?? this.state ?? '').toString();
                },
                formatMoney(n) {
                    return `${this.currency} ${safeNumber(n, 0).toFixed(2)}`;
                },

                // products filtered
                get activeProducts() {
                    return this.products.filter(p => Number(p.status) === 1);
                },

                findProductById(id) {
                    return this.products.find(p => Number(p.id) === Number(id)) || null;
                },

                rowProduct(i) {
                    const pid = this.rows?.[i]?.product_id;
                    return pid ? this.findProductById(pid) : null;
                },

                rowHasColors(i) {
                    const p = this.rowProduct(i);
                    return !!(p && Array.isArray(p.colors) && p.colors.length);
                },
                rowHasSizes(i) {
                    const p = this.rowProduct(i);
                    return !!(p && Array.isArray(p.sizes) && p.sizes.length);
                },

                // ✅ filter options by stock if stock exists (otherwise show all)
                rowColors(i) {
                    const p = this.rowProduct(i);
                    if (!p) return [];
                    const colors = Array.isArray(p.colors) ? p.colors : [];
                    const stock = Array.isArray(p.stock) ? p.stock : [];
                    if (!stock.length) return colors;

                    const row = this.rows[i];
                    const sizeId = row.size_id ? Number(row.size_id) : null;

                    const allowedColorIds = new Set(
                        stock
                        .filter(r => Number(r.qty || 0) > 0)
                        .filter(r => sizeId ? Number(r.sizeId) === sizeId : true)
                        .map(r => Number(r.colorId))
                    );

                    return colors.filter(c => allowedColorIds.has(Number(c.id)));
                },

                rowSizes(i) {
                    const p = this.rowProduct(i);
                    if (!p) return [];
                    const sizes = Array.isArray(p.sizes) ? p.sizes : [];
                    const stock = Array.isArray(p.stock) ? p.stock : [];
                    if (!stock.length) return sizes;

                    const row = this.rows[i];
                    const colorId = row.color_id ? Number(row.color_id) : null;

                    const allowedSizeIds = new Set(
                        stock
                        .filter(r => Number(r.qty || 0) > 0)
                        .filter(r => colorId ? Number(r.colorId) === colorId : true)
                        .map(r => Number(r.sizeId))
                    );

                    return sizes.filter(s => allowedSizeIds.has(Number(s.id)));
                },

                rowMaxQty(i) {
                    const p = this.rowProduct(i);
                    if (!p) return null;
                    const stock = Array.isArray(p.stock) ? p.stock : [];
                    if (!stock.length) return null; // unknown: product-level stock not represented here

                    const row = this.rows[i];
                    const needColor = this.rowHasColors(i);
                    const needSize = this.rowHasSizes(i);

                    const colorOk = !needColor || !!row.color_id;
                    const sizeOk = !needSize || !!row.size_id;
                    if (!colorOk || !sizeOk) return null;

                    const match = stock.find(r =>
                        Number(r.colorId || 0) === Number(row.color_id || 0) &&
                        Number(r.sizeId || 0) === Number(row.size_id || 0)
                    );
                    return match ? Number(match.qty || 0) : 0;
                },

                rowVariantHint(i) {
                    const m = this.rowMaxQty(i);
                    if (m === null) return '';
                    return m === 0 ? 'Out of stock' : `Available: ${m}`;
                },

                // ✅ pricing logic (variant matrix -> fallback)
                normId(v) {
                    if (v === null || v === undefined || v === '') return null;
                    const n = Number(v);
                    return Number.isFinite(n) ? n : null;
                },
                priceKey(colorId, sizeId) {
                    const cid = this.normId(colorId);
                    const sid = this.normId(sizeId);
                    return `${cid === null ? 'na' : cid}|${sid === null ? 'na' : sid}`;
                },
                effectivePriceFor(p, colorId, sizeId) {
                    const basePrice = Number(p?.basePrice || 0);
                    const baseDiscount = (p?.baseDiscount !== null && p?.baseDiscount !== undefined && Number(p
                            .baseDiscount) > 0) ?
                        Number(p.baseDiscount) : null;

                    const prices = Array.isArray(p?.prices) ? p.prices : [];
                    const idx = {};
                    for (const r of prices) {
                        idx[this.priceKey(r.colorId ?? null, r.sizeId ?? null)] = r;
                    }

                    const candidates = [
                        this.priceKey(colorId, sizeId),
                        this.priceKey(colorId, null),
                        this.priceKey(null, sizeId),
                        this.priceKey(null, null),
                    ];

                    for (const k of candidates) {
                        const row = idx[k];
                        if (!row) continue;
                        const price = (row.price !== null && row.price !== undefined && Number(row.price) > 0) ? Number(row
                            .price) : basePrice;
                        const disc = (row.discounted_price !== null && row.discounted_price !== undefined && Number(row
                                .discounted_price) > 0) ?
                            Number(row.discounted_price) : null;
                        return (disc !== null && disc < price) ? disc : price;
                    }

                    return (baseDiscount !== null && baseDiscount < basePrice) ? baseDiscount : basePrice;
                },

                resolveStockIdFor(p, colorId, sizeId) {
                    const stock = Array.isArray(p?.stock) ? p.stock : [];
                    if (!stock.length) return null;
                    const cid = this.normId(colorId) ?? 0;
                    const sid = this.normId(sizeId) ?? 0;
                    const row = stock.find(r => Number(r.colorId || 0) === Number(cid) && Number(r.sizeId || 0) === Number(
                        sid));
                    return row ? Number(row.id) : null;
                },

                // called when product selected
                onProductChange(i, e) {
                    const row = this.rows[i];
                    const p = this.findProductById(row.product_id);

                    row.product_name = p ? String(p.name || '') : '';
                    row.color_id = null;
                    row.size_id = null;
                    row.product_stock_id = null;

                    // base fallback price first
                    row.price = p ? this.effectivePriceFor(p, null, null) : 0;

                    if (!p) {
                        this.recalc();
                        return;
                    }

                    // ✅ pick first AVAILABLE color (stock-filtered when stock exists)
                    if (this.rowHasColors(i)) {
                        const colors = this.rowColors(i); // with size_id = null => returns colors that have any in-stock
                        row.color_id = colors.length ? Number(colors[0].id) : null;
                    }

                    // ✅ pick first AVAILABLE size (after color is set)
                    if (this.rowHasSizes(i)) {
                        const sizes = this.rowSizes(i); // will respect chosen color if stock exists
                        row.size_id = sizes.length ? Number(sizes[0].id) : null;
                    }

                    this.$nextTick(() => {
                        this.onVariantChange(i); // sets stock_id + recalculates price
                        this.recalc();
                    });
                },
                onVariantChange(i) {
                    const row = this.rows[i];
                    const p = this.rowProduct(i);
                    if (!p) return;

                    // if stock exists, and current selection becomes invalid, clear it
                    const stock = Array.isArray(p.stock) ? p.stock : [];
                    if (stock.length) {
                        // clamp color to allowed list
                        if (row.color_id && this.rowHasColors(i) && !this.rowColors(i).some(c => Number(c.id) === Number(row
                                .color_id))) {
                            row.color_id = null;
                        }
                        // clamp size to allowed list
                        if (row.size_id && this.rowHasSizes(i) && !this.rowSizes(i).some(s => Number(s.id) === Number(row
                                .size_id))) {
                            row.size_id = null;
                        }
                    }
                    // after clamp, if cleared, auto-pick first available
                    if (this.rowHasColors(i) && !row.color_id) {
                        const colors = this.rowColors(i);
                        row.color_id = colors.length ? Number(colors[0].id) : null;
                    }
                    if (this.rowHasSizes(i) && !row.size_id) {
                        const sizes = this.rowSizes(i);
                        row.size_id = sizes.length ? Number(sizes[0].id) : null;
                    }
                    // update stock id when selection complete enough
                    const needColor = this.rowHasColors(i);
                    const needSize = this.rowHasSizes(i);
                    const ready = (!needColor || !!row.color_id) && (!needSize || !!row.size_id);

                    row.product_stock_id = ready ? this.resolveStockIdFor(p, row.color_id, row.size_id) : null;

                    // update price from matrix (even if partial selection)
                    row.price = this.effectivePriceFor(p, row.color_id, row.size_id);

                    this.recalc();
                },

                // totals
                recalc() {
                    this.itemsSubtotal = this.rows.reduce((sum, r) => {
                        return sum + (safeNumber(r.price, 0) * Math.max(1, parseInt(r.quantity || 1, 10)));
                    }, 0);
                },

                // shipping logic (unchanged)
                normalizeTaxRate(v) {
                    if (v === null || v === undefined || v === '') return null;
                    const n = Number(v);
                    if (!Number.isFinite(n)) return null;
                    return n > 1 ? (n / 100) : n;
                },
                get resolvedShippingOptions() {
                    const country = (this.country || '').trim();
                    const stateCodeLower = (this.state || '').trim().toLowerCase();
                    const stateNameLower = (this.selectedStateName || '').trim().toLowerCase();
                    if (!country) return [];

                    const list = this.shippingOptions
                        .filter(o => String((o.country || '')).trim() === country)
                        .map(o => {
                            const statesLower = (Array.isArray(o.states) ? o.states : [])
                                .map(s => String(s).trim().toLowerCase())
                                .filter(Boolean);

                            const isCountryWide = statesLower.length === 0;
                            const matches = isCountryWide ? true : (
                                (stateCodeLower && statesLower.includes(stateCodeLower)) ||
                                (stateNameLower && statesLower.includes(stateNameLower))
                            );

                            const taxRate = this.normalizeTaxRate(o.tax_percentage);
                            return {
                                ...o,
                                id: String(o.id),
                                name: String(o.name || 'Shipping'),
                                price: safeNumber(o.price, 0),
                                delivery_time: String(o.delivery_time || ''),
                                _scopeLabel: isCountryWide ? `${country} • Country-wide` :
                                    `${country} • ${this.selectedStateName}`,
                                _ok: matches,
                                _taxRate: (taxRate !== null ? taxRate : this.defaultTaxRate),
                            };
                        }).filter(o => o._ok);

                    if (!list.some(o => String(o.id) === String(this.selectedShippingId))) {
                        this.selectedShippingId = (list.length === 1) ? String(list[0].id) : null;
                    }
                    return list;
                },
                get selectedShipping() {
                    const list = this.resolvedShippingOptions;
                    if (!this.selectedShippingId && list.length === 1) return list[0];
                    return list.find(o => String(o.id) === String(this.selectedShippingId)) || list[0] || null;
                },
                get effectiveTaxRate() {
                    return this.selectedShipping ? (this.selectedShipping._taxRate ?? this.defaultTaxRate) : this
                        .defaultTaxRate;
                },
                get previewShipping() {
                    return this.selectedShipping ? safeNumber(this.selectedShipping.price, this.defaultShippingPrice) :
                        this.defaultShippingPrice;
                },
                get previewTax() {
                    return safeNumber(this.itemsSubtotal * this.effectiveTaxRate, 0);
                },
                get grandTotal() {
                    return safeNumber(this.itemsSubtotal + this.previewTax + this.previewShipping, 0);
                },
                get shippingHint() {
                    if (!this.country) return 'Select country/state';
                    const c = (this.country || '').trim();
                    const s = (this.selectedStateName || '').trim();
                    return s ? `${c} • ${s}` : c;
                },

                addRow() {
                    this.rows.push(newRow());
                    this.recalc();
                },
                removeRow(i) {
                    this.rows.splice(i, 1);
                    if (this.rows.length === 0) this.rows.push(newRow());
                    this.recalc();
                },

                onLocationChange(resetState = false) {
                    if (resetState) {
                        const entries = Object.entries(this.statesForCountry());
                        const firstCode = entries.length ? entries[0][0] : '';
                        if (!Object.keys(this.statesForCountry()).includes(this.state)) this.state = firstCode;
                    }
                    this.selectedShippingId = null;
                },

                init() {
                    if (!this.state) {
                        const entries = Object.entries(this.statesForCountry());
                        this.state = entries.length ? entries[0][0] : '';
                    }
                    if (this.billingSameAsShipping) this.billingAddress = this.shippingAddress;
                    this.recalc();
                }
            }
        }

        function newRow() {
            return {
                uid: (crypto?.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random())),
                product_id: '',
                product_name: '',
                price: 0,
                quantity: 1,

                // ✅ variant fields
                color_id: null,
                size_id: null,
                product_stock_id: null,
            };
        }
    </script>
@endsection
