@extends('layouts.app')

@section('content')
    @php
        $colorHexById = $product->colors->pluck('color_code', 'id')->map(fn($v) => strtoupper($v));
        $sizeNameById = $product->sizes->pluck('size', 'id');

        // Each row = one variant (color?, size?, qty)
        $stockPayload = $product->stock
            ->map(function ($row) use ($colorHexById, $sizeNameById) {
                return [
                    'id' => $row->id,
                    'colorId' => $row->color_id, // NEW
                    'sizeId' => $row->size_id, // NEW
                    'colorHex' => $row->color_id ? $colorHexById[$row->color_id] ?? null : null,
                    'size' => $row->size_id ? $sizeNameById[$row->size_id] ?? null : null,
                    'qty' => (int) ($row->available_qty ?? $row->quantity_on_hand),
                ];
            })
            ->values();
    @endphp


    <div class="min-h-screen py-3" x-data="{ showModal: false, modalImage: '' }">
        <div x-data="productPage(@js([
    'images' => $product->images
        ->flatMap(function ($img) use ($product) {
            $hex = $img->color_code ? strtoupper($img->color_code) : null;

            // If image has no color, duplicate it for all colors
            if (!$hex && $product->colors->count() > 0) {
                return $product->colors->map(function ($c) use ($img) {
                    return [
                        'src' => asset('storage/' . $img->image_path),
                        'alt' => $img->alt_text ?? __('product.image_alt'),
                        'colorHex' => strtoupper($c->color_code),
                    ];
                });
            }

            // Normal case: keep as-is
            return [
                [
                    'src' => asset('storage/' . $img->image_path),
                    'alt' => $img->alt_text ?? __('product.image_alt'),
                    'colorHex' => $hex,
                ],
            ];
        })
        ->values(),

    'colors' => $product->colors
        ->map(
            fn($c) => [
                'id' => $c->id, // NEW
                'name' => $c->name,
                'hex' => strtoupper($c->color_code),
            ],
        )
        ->values(),
    'sizes' => $product->sizes
        ->map(
            fn($s) => [
                'id' => $s->id, // NEW
                'size' => $s->size,
            ],
        )
        ->values(),
    'stock' => $stockPayload,
    'productTotalQty' => $productTotalQty ?? 0,
]))" <div class="grid grid-cols-1 gap-8 md:grid-cols-2 place-items-center">
            {{-- Left Panel --}}
            <div class="w-full h-full">
                {{-- Carousel --}}
                <div class="relative w-full h-full rounded-lg overflow-hidden">
                    <div>
                        <!-- Slides -->
                        <template x-for="(img, i) in displayImages" :key="i">
                            <div x-show="index === i" x-transition.opacity.duration.300ms
                                class="absolute inset-0 flex items-center justify-center justify-self-center w-fit"
                                @mouseenter="paused = true; hover = true"
                                @mouseleave="paused = false; hover = false; originX = 50; originY = 50"
                                @mousemove="onMove($event)">
                                <img :src="img.src" :alt="img.alt"
                                    class="max-w-full max-h-full object-contain select-none pointer-events-none rounded"
                                    draggable="false" :style="imgStyle(i)">
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Controls under the image -->
                <div class="flex items-center justify-center gap-6 mt-3">
                    <!-- Prev Arrow -->
                    <button @click="index = (index - 1 + displayImages.length) % displayImages.length"
                        class="bg-black/70 text-white rounded-full w-8 h-8 flex justify-center items-center hover:bg-black">
                        &#10094;
                    </button>

                    <!-- Dots -->
                    <div class="flex items-center gap-2">
                        <template x-for="(img, i) in displayImages" :key="'dot_' + i">
                            <button @click="go(i)" class="w-2.5 h-2.5 rounded-full transition"
                                :class="i === index ? 'bg-black' : 'bg-gray-400'">
                            </button>
                        </template>
                    </div>

                    <!-- Next Arrow -->
                    <button @click="index = (index + 1) % displayImages.length"
                        class="bg-black/70 text-white rounded-full w-8 h-8 flex justify-center items-center hover:bg-black">
                        &#10095;
                    </button>
                </div>
            </div>

            {{-- Right panel --}}
            <div class="w-full">
                <h1 class="mb-2 text-3xl montaga-semibold text-charcoal">
                    {{ $product->name }}
                </h1>

                <p class="mb-4 text-lg text-gray-600">
                    {{ __('product.currency_aed') }} {{ number_format($product->price, 2) }}
                </p>

                @if ($product->description)
                    <h2 class="mb-2 text-2xl font-bold text-charcoal">{{ __('product.description') }}</h2>
                    {!! $product->description !!}
                @endif

                {{-- Color selector (if any) --}}

                <template x-if="colors.length">
                    <div class="mt-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="c in colors" :key="c.id">
                                <button type="button" @click="selectColor(c)" :disabled="!isColorAvailable(c.hex)"
                                    class="relative inline-flex items-center gap-2 px-3 py-1.5 rounded-full border transition focus:outline-none"
                                    :class="[
                                        selectedColorHex === c.hex ? 'border-black bg-black text-white' :
                                        'border-gray-300 bg-white',
                                        !isColorAvailable(c.hex) ? 'opacity-40 cursor-not-allowed' : ''
                                    ]">
                                    <span class="inline-block w-4 h-4 rounded-full border"
                                        :style="`background:${c.hex}`"></span>
                                    <span class="text-sm" x-text="c.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>


                {{-- Size selector (if any) --}}

                <template x-if="sizes.length">
                    <div class="mt-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Size</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in sizes" :key="s.id">
                                <button type="button" @click="selectSize(s)" :disabled="!isSizeAvailable(s.size)"
                                    class="px-4 py-1.5 rounded-full border transition"
                                    :class="[
                                        selectedSize === s.size ? 'border-black bg-black text-white' :
                                        'border-gray-300 bg-white',
                                        !isSizeAvailable(s.size) ? 'opacity-40 cursor-not-allowed' : ''
                                    ]">
                                    <span class="text-sm" x-text="s.size"></span>
                                </button>
                            </template>
                            <button type="button"
                                @click="showModal = true; modalImage = '{{ asset('images/size-chart.jpeg') }}'"
                                class="px-4 py-1.5 rounded-full border-2 transition hover:border-black">
                                <span class="text-sm">Size Chart</span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Add to cart --}}
                <form action="{{ route('cart.add', ['id' => $product->id]) }}" method="POST" class="mt-6">
                    @csrf

                    <input type="hidden" name="color_code" :value="selectedColorHex || ''">
                    <input type="hidden" name="size" :value="selectedSize || ''">
                    <input type="hidden" name="product_stock_id" :value="currentVariantId() || ''">
                    <input type="hidden" name="color_id" :value="currentColorId() || ''">
                    <input type="hidden" name="size_id" :value="currentSizeId() || ''">


                    {{-- Quantity --}}
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
            <h1 class="text-3xl montaga-regular m-6 mt-24">
                {{ __('product.more_like_this') }}
            </h1>
        @endif

        <div class="flex flex-wrap gap-5 m-5">
            @foreach ($smiliarProducts as $sp)
                <div
                    class="overflow-hidden !transition bg-white rounded-lg shadow-md !duration-500 hover:shadow-2xl fade-up w-[250px]">
                    @if ($sp->images->count())
                        <div
                            class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-1">
                            <img class="h-full object-contain cursor-zoom-in rounded-md" alt="{{ $sp->name }}"
                                src="{{ asset('storage/' . $sp->images->first()->image_path) }}"
                                @click="showModal = true; modalImage = '{{ asset('storage/' . $sp->images->first()->image_path) }}'">

                        </div>
                    @endif

                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-charcoal">
                            {{ app()->getLocale() === 'ar' && $sp->name_ar ? $sp->name_ar : $sp->name }}
                        </h3>
                        <p
                            class="mt-1 text-gray-600 w-min"{{ Str::limit(app()->getLocale() === 'ar' && $sp->description_ar ? $sp->description_ar : $sp->description, 80) }}</p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xl font-bold text-gray-600">
                                {{ __('product.currency_aed') }} {{ number_format($sp->price, 2) }}
                            </span>
                            <a href="{{ route('products.show', $sp->id) }}"
                                class="inline-block px-3 py-1 text-white transition bg-gray-600 rounded hover:bg-gray-700">
                                {{ __('product.view') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div x-cloak x-show="showModal" x-transition
        class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
        @keydown.escape.window="showModal = false" role="dialog">
        <div class="relative max-w-full max-h-screen">
            <img @click.outside="showModal = false" :src="modalImage"
                class="max-w-full max-h-[90vh] rounded shadow-xl" alt="">
        </div>
    </div>
    </div>
    </div>



    <script>
        function productPage(init) {
            return {
                images: Array.isArray(init.images) ? init.images : [],
                colors: Array.isArray(init.colors) ? init.colors : [], // [{id,name,hex}]
                sizes: Array.isArray(init.sizes) ? init.sizes : [], // [{id,size}]
                stock: Array.isArray(init.stock) ? init.stock : [], // [{id,colorId,sizeId,colorHex,size,qty}]
                productTotalQty: Number.isFinite(init.productTotalQty) ? init.productTotalQty : 0,

                // selections (now include ids)
                selectedColorHex: null,
                selectedColorId: null, // NEW
                selectedSize: null,
                selectedSizeId: null, // NEW
                qty: 1,

                // carousel state...
                index: 0,
                paused: false,
                hover: false,
                zoom: 2,
                originX: 50,
                originY: 50,
                intervalId: null,

                get displayImages() {
                    if (!this.selectedColorHex) return this.images;
                    const filtered = this.images.filter(img => (img.colorHex || null) === this.selectedColorHex);
                    return filtered.length ? filtered : this.images;
                },

                initSelections() {
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
                    this.start();
                },

                // select handlers now receive the whole object
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

                hasColorOptions() {
                    return this.colors.length > 0
                },
                hasSizeOptions() {
                    return this.sizes.length > 0
                },

                // Find exact variant row qty; returns 0 if not found
                qtyFor(colorHex, size) {
                    const row = this.stock.find(r =>
                        (r.colorHex || null) === (colorHex || null) &&
                        (r.size || null) === (size || null)
                    );
                    return row ? Math.max(0, parseInt(row.qty, 10) || 0) : 0;
                },

                // Variant id for cart (unchanged)
                currentVariantId() {
                    if (this.hasColorOptions() && this.hasSizeOptions()) {
                        if (!this.selectedColorHex || !this.selectedSize) return null;
                        const row = this.stock.find(r => r.colorHex === this.selectedColorHex && r.size === this
                            .selectedSize);
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

                // NEW: expose color_id / size_id for hidden inputs
                currentColorId() {
                    if (this.selectedColorId) return this.selectedColorId;
                    if (!this.selectedColorHex) return null;
                    const c = this.colors.find(x => x.hex === this.selectedColorHex);
                    return c ? c.id : null;
                },
                currentSizeId() {
                    if (this.selectedSizeId) return this.selectedSizeId;
                    if (!this.selectedSize) return null;
                    const s = this.sizes.find(x => x.size === this.selectedSize);
                    return s ? s.id : null;
                },

                // availability / UI
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

                // carousel (unchanged)
                start() {
                    this.stop();
                    if (this.displayImages.length <= 1) return;
                    this.intervalId = setInterval(() => {
                        if (!this.paused) this.next()
                    }, 5000);
                },
                stop() {
                    if (this.intervalId) {
                        clearInterval(this.intervalId);
                        this.intervalId = null;
                    }
                },
                next() {
                    this.index = (this.index + 1) % this.displayImages.length;
                },
                prev() {
                    this.index = (this.index - 1 + this.displayImages.length) % this.displayImages.length;
                },
                go(i) {
                    this.index = i;
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
