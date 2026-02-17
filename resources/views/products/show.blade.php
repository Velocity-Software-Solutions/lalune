@extends('layouts.app')

@section('title', $product->name)

@push('head')
    <meta name="description" content="{{ Str::limit(strip_tags($product->description), 150) }}">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
    @php
        /*
        Page notes (kept ONLY here at the top):
        - Manual carousel only: no auto-advance timer, no start()/stop() interval logic.
        - Variant-aware pricing:
          * If a prices-row exists for a variant: use that row price/discount (discounted_price > 0), ignore base discount.
          * If no prices-row: fall back to base price + base discount (discount_price > 0).
          * When selection is incomplete, show a range computed from in-stock variants (effective prices).
        - Images:
          * Filter by selected color if present, and keep thumbnails first if flagged.
        - Stock:
          * Availability and max quantity are based on variant stock rows.
        */

        $colorHexById = $product->colors->pluck('color_code', 'id')->map(fn ($v) => strtoupper($v));
        $sizeNameById = $product->sizes->pluck('size', 'id');

        $stockPayload = $product->stock
            ->map(function ($row) use ($colorHexById, $sizeNameById) {
                return [
                    'id' => $row->id,
                    'colorId' => $row->color_id,
                    'sizeId' => $row->size_id,
                    'colorHex' => $row->color_id ? ($colorHexById[$row->color_id] ?? null) : null,
                    'size' => $row->size_id ? ($sizeNameById[$row->size_id] ?? null) : null,
                    'qty' => (int) ($row->available_qty ?? $row->quantity_on_hand),
                ];
            })
            ->values();

        $pricePayload = collect();

        if (method_exists($product, 'prices') && $product->relationLoaded('prices')) {
            $pricePayload = $product->prices
                ->map(function ($row) {
                    return [
                        'colorId' => $row->color_id,
                        'sizeId' => $row->size_id,
                        'price' => is_null($row->price) ? null : (float) $row->price,
                        'discounted_price' =>
                            !is_null($row->discounted_price) && (float) $row->discounted_price > 0
                                ? (float) $row->discounted_price
                                : null,
                    ];
                })
                ->values();
        } elseif (method_exists($product, 'prices')) {
            $pricePayload = $product->prices
                ->map(function ($row) {
                    return [
                        'colorId' => $row->color_id,
                        'sizeId' => $row->size_id,
                        'price' => is_null($row->price) ? null : (float) $row->price,
                        'discounted_price' =>
                            !is_null($row->discounted_price) && (float) $row->discounted_price > 0
                                ? (float) $row->discounted_price
                                : null,
                    ];
                })
                ->values();
        }

        $baseDiscount =
            $product->discount_price !== null && (float) $product->discount_price > 0 ? (float) $product->discount_price : null;
    @endphp

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-300 text-red-700 rounded">
            <p class="font-semibold mb-2">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="min-h-screen py-3" x-data="{ showModal: false, modalImage: '' }">
        <div
            x-data="productPage(@js([
                'images' => $product->images
                    ->map(function ($img) {
                        return [
                            'src' => asset('storage/' . $img->image_path),
                            'alt' => $img->alt_text ?? __('product.image_alt'),
                            'colorHex' => $img->color_code ? strtoupper($img->color_code) : null,
                            'isThumb' => (bool) ($img->thumbnail ?? 0),
                        ];
                    })
                    ->values(),
                'colors' => $product->colors
                    ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => strtoupper($c->color_code)])
                    ->values(),
                'sizes' => $product->sizes
                    ->map(fn($s) => ['id' => $s->id, 'size' => $s->size])
                    ->values(),
                'stock' => $stockPayload,
                'prices' => $pricePayload,
                'productBasePrice' => (float) $product->price,
                'productBaseDiscount' => $baseDiscount,
                'productTotalQty' => $productTotalQty ?? 0,
            ]))"
            x-init="initSelections()"
        >
            <div class="grid grid-cols-1 gap-16 lg:gap-8 md:grid-cols-2 place-items-center">
                <div class="w-full h-full">
                    <div class="relative w-full h-full rounded-lg overflow-hidden min-h-96 flex justify-center items-center">
                        <div>
                            <template x-for="(img, i) in displayImages" :key="i">
                                <div x-show="index === i" x-transition.opacity.duration.300ms
                                    class="flex items-center justify-center w-fit m-5"
                                    @mouseenter="hover = true"
                                    @mouseleave="hover = false; originX = 50; originY = 50"
                                    @mousemove="onMove($event)"
                                >
                                    <img
                                        :src="img.src"
                                        :alt="img.alt"
                                        class="max-w-full max-h-full object-contain select-none pointer-events-none rounded"
                                        draggable="false"
                                        :style="imgStyle(i)"
                                    >
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-6 mt-3">
                        <button type="button" @click="prev()"
                            class="bg-black/70 text-white rounded-full w-8 h-8 flex justify-center items-center hover:bg-black">
                            &#10094;
                        </button>

                        <div class="flex items-center gap-2">
                            <template x-for="(img, i) in displayImages" :key="'dot_' + i">
                                <button type="button" @click="go(i)" class="w-2.5 h-2.5 rounded-full transition"
                                    :class="i === index ? 'bg-black' : 'bg-gray-400'">
                                </button>
                            </template>
                        </div>

                        <button type="button" @click="next()"
                            class="bg-black/70 text-white rounded-full w-8 h-8 flex justify-center items-center hover:bg-black">
                            &#10095;
                        </button>
                    </div>
                </div>

                <div class="w-full px-5">
                    <h1 class="mb-2 text-3xl montaga-semibold text-charcoal">
                        {{ $product->name }}
                    </h1>

                    <div class="mb-4">
                        <template x-if="priceView().mode === 'single'">
                            <div>
                                <template x-if="priceView().discount !== null && priceView().discount < priceView().price">
                                    <div class="flex items-center gap-3">
                                        <p class="text-2xl font-semibold text-charcoal">
                                            {{ __('product.currency_aed') }}
                                            <span x-text="formatMoney(priceView().discount)"></span>
                                        </p>
                                        <p class="text-lg text-gray-400 line-through">
                                            {{ __('product.currency_aed') }}
                                            <span x-text="formatMoney(priceView().price)"></span>
                                        </p>
                                    </div>
                                </template>

                                <template x-if="!(priceView().discount !== null && priceView().discount < priceView().price)">
                                    <p class="text-2xl font-semibold text-charcoal">
                                        {{ __('product.currency_aed') }}
                                        <span x-text="formatMoney(priceView().price)"></span>
                                    </p>
                                </template>
                            </div>
                        </template>

                        <template x-if="priceView().mode === 'range'">
                            <div class="flex items-baseline gap-2">
                                <p class="text-2xl font-semibold text-charcoal">
                                    {{ __('product.currency_aed') }}
                                    <span x-text="`${formatMoney(priceView().min)} – ${formatMoney(priceView().max)}`"></span>
                                </p>
                                <span class="text-xs text-gray-500">(select options)</span>
                            </div>
                        </template>

                        <p class="text-xs text-gray-500 mt-1" x-show="priceView().isVariantAware">
                            Price may vary by selected options.
                        </p>
                    </div>

                    @if ($product->description)
                        <h2 class="mb-2 text-2xl font-bold text-charcoal">
                            {{ __('product.description') }}
                        </h2>
                        {!! $product->description !!}
                    @endif

                    <template x-if="colors.length">
                        <div class="mt-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="c in colors" :key="c.id">
                                    <button type="button" @click="selectColor(c)" :disabled="!isColorAvailable(c.hex)"
                                        class="relative inline-flex items-center gap-2 px-3 py-1.5 rounded-full border transition focus:outline-none"
                                        :class="[
                                            selectedColorHex === c.hex ? 'border-black bg-black text-white' : 'border-gray-300 bg-white',
                                            !isColorAvailable(c.hex) ? 'opacity-40 cursor-not-allowed' : ''
                                        ]">
                                        <span class="inline-block w-4 h-4 rounded-full border" :style="`background:${c.hex}`"></span>
                                        <span class="text-sm" x-text="c.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="sizes.length">
                        <div class="mt-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Size</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="s in sizes" :key="s.id">
                                    <button type="button" @click="selectSize(s)" :disabled="!isSizeAvailable(s.size)"
                                        class="px-4 py-1.5 rounded-full border transition"
                                        :class="[
                                            selectedSize === s.size ? 'border-black bg-black text-white' : 'border-gray-300 bg-white',
                                            !isSizeAvailable(s.size) ? 'opacity-40 cursor-not-allowed' : ''
                                        ]">
                                        <span class="text-sm" x-text="s.size"></span>
                                    </button>
                                </template>

                                <button type="button"
                                    @click="$root.showModal = true; $root.modalImage = '{{ asset('images/size-chart.jpeg') }}'"
                                    class="px-4 py-1.5 rounded-full border-2 transition hover:border-black">
                                    <span class="text-sm">Size Chart</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <form action="{{ route('cart.add', ['id' => $product->id]) }}" method="POST" class="mt-6">
                        @csrf

                        <input type="hidden" name="color_code" :value="selectedColorHex || ''">
                        <input type="hidden" name="size" :value="selectedSize || ''">
                        <input type="hidden" name="product_stock_id" :value="currentVariantId() || ''">
                        <input type="hidden" name="color_id" :value="currentColorId() || ''">
                        <input type="hidden" name="size_id" :value="currentSizeId() || ''">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('product.quantity') }}</label>

                            <div class="flex items-center rounded-md overflow-hidden w-28">
                                <button type="button" @click="if(qty > 1) qty--" :disabled="qty <= 1"
                                    class="w-9 py-1 bg-gray-200 hover:bg-gray-300 rounded-l disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="material-icons text-lg">remove</span>
                                </button>

                                <input type="text" readonly name="quantity" :value="qty"
                                    class="w-10 text-center border-y border-gray-300 focus:ring-0 focus:outline-none py-1">

                                <button type="button" @click="if(qty < maxQty()) qty++" :disabled="qty >= maxQty()"
                                    class="w-9 py-1 bg-gray-200 hover:bg-gray-300 rounded-r disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="material-icons text-lg">add</span>
                                </button>
                            </div>

                            <p class="mt-2 text-xs text-gray-500" x-text="availabilityLabel()"></p>
                        </div>

                        <button type="submit" :disabled="!canAddToCart()"
                            class="px-6 py-2 text-white rounded-lg transition flex items-center gap-3"
                            :class="canAddToCart() ? 'bg-black hover:bg-gray-800' : 'bg-gray-400 cursor-not-allowed'">
                            <span class="material-icons">add_shopping_cart</span>
                            {{ __('product.add_to_cart') }}
                        </button>
                    </form>
                </div>
            </div>

            <div>
                @if ($smiliarProducts->isNotEmpty())
                    <h1 class="text-3xl montaga-regular m-6 mt-14">
                        {{ __('product.more_like_this') }}
                    </h1>
                @endif

                <div class="flex flex-wrap gap-5 m-5">
                    @foreach ($smiliarProducts as $sp)
                        <div class="overflow-hidden !transition bg-white rounded-lg shadow-md !duration-500 hover:shadow-2xl fade-up w-[250px]">
                            @if ($sp->images->count())
                                <div class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-1">
                                    <img class="h-full object-contain cursor-zoom-in rounded-md"
                                        alt="{{ $sp->name }}"
                                        src="{{ asset('storage/' . $sp->images->first()->image_path) }}"
                                        @click="$root.showModal = true; $root.modalImage = '{{ asset('storage/' . $sp->images->first()->image_path) }}'">
                                </div>
                            @endif

                            <div class="p-4">
                                <h3 class="text-lg font-semibold text-charcoal">
                                    {{ app()->getLocale() === 'ar' && $sp->name_ar ? $sp->name_ar : $sp->name }}
                                </h3>
                                <p class="mt-1 text-gray-600 w-min">
                                    {{ Str::limit(app()->getLocale() === 'ar' && $sp->description_ar ? $sp->description_ar : $sp->description, 80) }}
                                </p>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xl font-bold text-gray-600">
                                        {{ __('product.currency_aed') }} {{ number_format($sp->price, 2) }}
                                    </span>
                                    <a href="{{ route('products.show', $sp->slug) }}"
                                        class="inline-block px-3 py-1 text-white transition bg-gray-600 rounded hover:bg-gray-700">
                                        {{ __('product.view') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div x-cloak x-show="$root.showModal" x-transition
                class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
                @keydown.escape.window="$root.showModal = false" role="dialog">
                <div class="relative max-w-full max-h-screen">
                    <img @click.outside="$root.showModal = false" :src="$root.modalImage"
                        class="max-w-full max-h-[90vh] rounded shadow-xl" alt="">
                </div>
            </div>
        </div>
    </div>

    <script>
        function productPage(init) {
            return {
                images: Array.isArray(init.images) ? init.images : [],
                colors: Array.isArray(init.colors) ? init.colors : [],
                sizes: Array.isArray(init.sizes) ? init.sizes : [],
                stock: Array.isArray(init.stock) ? init.stock : [],
                prices: Array.isArray(init.prices) ? init.prices : [],

                productBasePrice: Number(init.productBasePrice ?? 0),
                productBaseDiscount: (init.productBaseDiscount === null || init.productBaseDiscount === undefined || Number(init.productBaseDiscount) <= 0)
                    ? null
                    : Number(init.productBaseDiscount),
                productTotalQty: Number.isFinite(init.productTotalQty) ? init.productTotalQty : 0,

                selectedColorHex: null,
                selectedColorId: null,
                selectedSize: null,
                selectedSizeId: null,
                qty: 1,

                index: 0,
                hover: false,
                zoom: 2,
                originX: 50,
                originY: 50,

                priceIndex: {},

                hasColorOptions() { return this.colors.length > 0 },
                hasSizeOptions() { return this.sizes.length > 0 },

                isSelectionComplete() {
                    const needColor = this.hasColorOptions() && !this.selectedColorHex;
                    const needSize = this.hasSizeOptions() && !this.selectedSize;
                    return !(needColor || needSize);
                },

                normId(v) {
                    if (v === null || v === undefined || v === '') return null;
                    const n = Number(v);
                    return Number.isFinite(n) ? n : null;
                },

                priceKey(colorId, sizeId) {
                    const cid = this.normId(colorId);
                    const sid = this.normId(sizeId);
                    return `${cid === null ? 'null' : cid}|${sid === null ? 'null' : sid}`;
                },

                buildPriceIndex() {
                    const idx = {};
                    for (const p of (this.prices || [])) {
                        const key = this.priceKey(p.colorId ?? null, p.sizeId ?? null);
                        idx[key] = {
                            colorId: this.normId(p.colorId ?? null),
                            sizeId: this.normId(p.sizeId ?? null),
                            price: (p.price !== null && p.price !== undefined && Number(p.price) > 0) ? Number(p.price) : null,
                            discounted_price: (p.discounted_price !== null && p.discounted_price !== undefined && Number(p.discounted_price) > 0)
                                ? Number(p.discounted_price)
                                : null,
                        };
                    }
                    this.priceIndex = idx;
                },

                get displayImages() {
                    let imgs = this.images;

                    if (this.selectedColorHex) {
                        imgs = imgs.filter(img => (img.colorHex || null) === this.selectedColorHex || img.colorHex == null);
                    }

                    const thumbs = imgs.filter(i => i.isThumb);
                    const rest = imgs.filter(i => !i.isThumb);
                    return thumbs.length ? [...thumbs, ...rest] : imgs;
                },

                initSelections() {
                    this.buildPriceIndex();

                    if (this.colors.length === 1) {
                        const c = this.colors[0];
                        this.selectedColorHex = c.hex;
                        this.selectedColorId = c.id;
                    }
                    if (this.sizes.length === 1) {
                        const s = this.sizes[0];
                        this.selectedSize = s.size;
                        this.selectedSizeId = s.id;
                    }
                },

                selectColor(c) {
                    if (!this.isColorAvailable(c.hex)) return;
                    this.selectedColorHex = c.hex;
                    this.selectedColorId = c.id;
                    this.index = 0;
                    this.clampQty();
                },

                selectSize(s) {
                    if (!this.isSizeAvailable(s.size)) return;
                    this.selectedSize = s.size;
                    this.selectedSizeId = s.id;
                    this.clampQty();
                },

                qtyFor(colorHex, size) {
                    const row = this.stock.find(r => (r.colorHex || null) === (colorHex || null) && (r.size || null) === (size || null));
                    return row ? Math.max(0, parseInt(row.qty, 10) || 0) : 0;
                },

                currentVariantId() {
                    if (this.hasColorOptions() && this.hasSizeOptions()) {
                        if (!this.selectedColorHex || !this.selectedSize) return null;
                        const row = this.stock.find(r => r.colorHex === this.selectedColorHex && r.size === this.selectedSize);
                        return row ? row.id : null;
                    }
                    if (this.hasColorOptions() && !this.hasSizeOptions()) {
                        if (!this.selectedColorHex) return null;
                        const row = this.stock.find(r => r.colorHex === this.selectedColorHex && (r.size || null) === null);
                        return row ? row.id : null;
                    }
                    if (!this.hasColorOptions() && this.hasSizeOptions()) {
                        if (!this.selectedSize) return null;
                        const row = this.stock.find(r => r.size === this.selectedSize && (r.colorHex || null) === null);
                        return row ? row.id : null;
                    }
                    return null;
                },

                currentColorId() {
                    if (this.selectedColorId) return this.normId(this.selectedColorId);
                    if (!this.selectedColorHex) return null;
                    const c = this.colors.find(x => x.hex === this.selectedColorHex);
                    return c ? this.normId(c.id) : null;
                },

                currentSizeId() {
                    if (this.selectedSizeId) return this.normId(this.selectedSizeId);
                    if (!this.selectedSize) return null;
                    const s = this.sizes.find(x => x.size === this.selectedSize);
                    return s ? this.normId(s.id) : null;
                },

                maxQty() {
                    if (this.hasColorOptions() && this.hasSizeOptions()) {
                        if (!this.selectedColorHex || !this.selectedSize) return 0;
                        return this.qtyFor(this.selectedColorHex, this.selectedSize);
                    }
                    if (this.hasColorOptions() && !this.hasSizeOptions()) {
                        if (!this.selectedColorHex) return 0;
                        return this.qtyFor(this.selectedColorHex, null);
                    }
                    if (!this.hasColorOptions() && this.hasSizeOptions()) {
                        if (!this.selectedSize) return 0;
                        return this.qtyFor(null, this.selectedSize);
                    }
                    return Math.max(0, parseInt(this.productTotalQty, 10) || 0);
                },

                clampQty() {
                    const m = this.maxQty();
                    if (m === 0) this.qty = 1;
                    else if (this.qty > m) this.qty = m;
                    else if (this.qty < 1) this.qty = 1;
                },

                isColorAvailable(hex) {
                    if (!this.hasSizeOptions()) return this.qtyFor(hex, null) > 0;
                    if (this.selectedSize) return this.qtyFor(hex, this.selectedSize) > 0;
                    return this.sizes.some(s => this.qtyFor(hex, s.size) > 0);
                },

                isSizeAvailable(size) {
                    if (!this.hasColorOptions()) return this.qtyFor(null, size) > 0;
                    if (this.selectedColorHex) return this.qtyFor(this.selectedColorHex, size) > 0;
                    return this.colors.some(c => this.qtyFor(c.hex, size) > 0);
                },

                canAddToCart() {
                    const needColor = this.hasColorOptions() && !this.selectedColorHex;
                    const needSize = this.hasSizeOptions() && !this.selectedSize;
                    return !needColor && !needSize && this.maxQty() > 0;
                },

                availabilityLabel() {
                    const m = this.maxQty();
                    if (this.hasColorOptions() && !this.selectedColorHex) return '{{ __('Select color') }}';
                    if (this.hasSizeOptions() && !this.selectedSize) return '{{ __('Select size') }}';
                    return m > 0 ? `{{ __('Available') }}: ${m}` : '{{ __('Out of stock') }}';
                },

                formatMoney(v) {
                    const n = Number(v);
                    if (!Number.isFinite(n)) return '0.00';
                    return n.toFixed(2);
                },

                priceRowFor(colorId, sizeId) {
                    const key = this.priceKey(colorId ?? null, sizeId ?? null);
                    return this.priceIndex[key] || null;
                },

                effectiveForVariant(colorId, sizeId) {
                    const basePrice = Number(this.productBasePrice || 0);
                    const baseDiscount = (this.productBaseDiscount !== null && Number(this.productBaseDiscount) > 0)
                        ? Number(this.productBaseDiscount)
                        : null;

                    const row = this.priceRowFor(colorId, sizeId);

                    if (row) {
                        const price = (row.price !== null && Number(row.price) > 0) ? Number(row.price) : basePrice;
                        const discount = (row.discounted_price !== null && Number(row.discounted_price) > 0) ? Number(row.discounted_price) : null;
                        const effective = (discount !== null && discount < price) ? discount : price;

                        return { price, discount, effective, hasVariant: true };
                    }

                    const price = basePrice;
                    const discount = baseDiscount;
                    const effective = (discount !== null && discount < price) ? discount : price;

                    return { price, discount, effective, hasVariant: false };
                },

                inStockVariantPairsFiltered() {
                    const pairs = this.stock
                        .filter(r => (Number(r.qty) || 0) > 0)
                        .map(r => ({ colorId: this.normId(r.colorId ?? null), sizeId: this.normId(r.sizeId ?? null) }));

                    const selectedColorId = this.currentColorId();
                    const selectedSizeId = this.currentSizeId();

                    return pairs.filter(p => {
                        if (this.hasColorOptions() && selectedColorId !== null && (p.colorId ?? null) !== selectedColorId) return false;
                        if (this.hasSizeOptions() && selectedSizeId !== null && (p.sizeId ?? null) !== selectedSizeId) return false;
                        return true;
                    });
                },

                rangeEffectivePrice() {
                    const pairs = this.inStockVariantPairsFiltered();
                    if (!pairs.length) return null;

                    let min = Infinity, max = -Infinity;
                    let variantAware = false;

                    for (const p of pairs) {
                        const res = this.effectiveForVariant(p.colorId, p.sizeId);
                        if (res.hasVariant) variantAware = true;

                        const val = Number(res.effective);
                        if (!Number.isFinite(val)) continue;
                        if (val < min) min = val;
                        if (val > max) max = val;
                    }

                    if (!Number.isFinite(min) || !Number.isFinite(max)) return null;
                    return { min, max, variantAware };
                },

                priceView() {
                    if (this.isSelectionComplete()) {
                        const cid = this.currentColorId();
                        const sid = this.currentSizeId();
                        const res = this.effectiveForVariant(cid, sid);

                        return {
                            mode: 'single',
                            price: res.price,
                            discount: res.discount,
                            isVariantAware: res.hasVariant,
                        };
                    }

                    const range = this.rangeEffectivePrice();
                    if (range) {
                        if (range.min === range.max) {
                            return {
                                mode: 'single',
                                price: range.min,
                                discount: null,
                                isVariantAware: range.variantAware,
                            };
                        }

                        return {
                            mode: 'range',
                            min: range.min,
                            max: range.max,
                            isVariantAware: range.variantAware,
                        };
                    }

                    const basePrice = Number(this.productBasePrice || 0);
                    const baseDiscount = (this.productBaseDiscount !== null && Number(this.productBaseDiscount) > 0)
                        ? Number(this.productBaseDiscount)
                        : null;

                    return {
                        mode: 'single',
                        price: basePrice,
                        discount: baseDiscount,
                        isVariantAware: false,
                    };
                },

                next() {
                    const len = this.displayImages.length;
                    if (len <= 1) return;
                    this.index = (this.index + 1) % len;
                },

                prev() {
                    const len = this.displayImages.length;
                    if (len <= 1) return;
                    this.index = (this.index - 1 + len) % len;
                },

                go(i) {
                    const len = this.displayImages.length;
                    if (!len) return;
                    const n = Number(i);
                    this.index = Number.isFinite(n) ? Math.max(0, Math.min(len - 1, n)) : 0;
                },

                onMove(e) {
                    const r = e.currentTarget.getBoundingClientRect();
                    this.originX = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
                    this.originY = Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100));
                },

                imgStyle(i) {
                    const active = this.index === i;
                    const scale = this.hover && active ? this.zoom : 1;
                    return `transform-origin:${this.originX}% ${this.originY}%; transform:scale(${scale}); transition: transform 120ms ease;`;
                },
            }
        }
    </script>
@endsection
