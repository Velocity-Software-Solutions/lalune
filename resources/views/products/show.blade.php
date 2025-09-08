@extends('layouts.app')

@section('content')
    <div class="min-h-screen py-3" x-data="{ showModal: false, modalImage: '' }">
        <div x-data="productPage(@js([
    // images: id? optional, src, alt, colorHex? (like '#000000' or null)
    'images' => $product->images
        ->map(
            fn($img) => [
                'src' => asset('storage/' . $img->image_path),
                'alt' => $img->alt_text ?? __('product.image_alt'),
                'colorHex' => $img->color_code ? strtoupper($img->color_code) : null,
            ],
        )
        ->values(),
    // colors: name + color_code
    'colors' => $product->colors
        ->map(
            fn($c) => [
                'name' => $c->name,
                'hex' => strtoupper($c->color_code),
            ],
        )
        ->values(),
    // sizes: simple array of strings
    'sizes' => $product->sizes->pluck('size')->values(),
]))" x-init="initSelections()" class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 place-items-center">

                {{-- Carousel --}}
                <div class="relative w-full h-96 rounded-lg overflow-hidden" @mouseenter="paused = true; hover = true"
                    @mouseleave="paused = false; hover = false; originX = 50; originY = 50">

                    <!-- Slides (bind to computed displayImages) -->
                    <template x-for="(img, i) in displayImages" :key="i">
                        <div x-show="index === i" x-transition.opacity.duration.300ms
                            class="absolute inset-0 flex items-center justify-center" @mousemove="onMove($event)">
                            <img :src="img.src" :alt="img.alt"
                                class="max-w-full max-h-full object-contain select-none pointer-events-none"
                                draggable="false" :style="imgStyle(i)">
                        </div>
                    </template>

                    <!-- Dots -->
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2">
                        <template x-for="(img, i) in displayImages" :key="'dot_' + i">
                            <button @click="go(i)" class="w-2.5 h-2.5 rounded-full transition"
                                :class="i === index ? 'bg-charcoal' : 'bg-gray-300'">
                            </button>
                        </template>
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
                        <p class="min-h-[2rem] w-full text-sm">
                            {{ $product->description }}
                        </p>
                    @endif

                    {{-- Color selector (if any) --}}
                    <template x-if="colors.length">
                        <div class="mt-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="c in colors" :key="c.name + c.hex">
                                    <button type="button" @click="selectColor(c.hex)"
                                        class="relative inline-flex items-center gap-2 px-3 py-1.5 rounded-full border
                             transition focus:outline-none"
                                        :class="selectedColorHex === c.hex ? 'border-black bg-black text-white' :
                                            'border-gray-300 bg-white'">
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
                                <template x-for="s in sizes" :key="'size_' + s">
                                    <button type="button" @click="selectSize(s)"
                                        class="px-4 py-1.5 rounded-full border transition"
                                        :class="selectedSize === s ? 'border-black bg-black text-white' :
                                            'border-gray-300 bg-white'">
                                        <span class="text-sm" x-text="s"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Add to cart --}}
                    <form action="{{ route('cart.add', $product->id) }}" method="POST" class="mt-6">
                        @csrf

                        {{-- Hidden fields to submit current choices --}}
                        <input type="hidden" name="color_code" :value="selectedColorHex || ''">
                        <input type="hidden" name="size" :value="selectedSize || ''">

                        {{-- Quantity --}}
                        <div class="mb-4" x-data="{ qty: 1, maxQty: {{ $product->stock_quantity }} }">
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('product.quantity') }}
                            </label>

                            <div class="flex items-center rounded-md overflow-hidden w-24">
                                <!-- Minus -->
                                <button type="button" @click="if(qty > 1) qty--" :disabled="qty <= 1"
                                    class="w-full py-1 bg-gray-200 hover:bg-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="material-icons text-lg">remove</span>
                                </button>

                                <!-- Qty display (form input) -->
                                <input type="text" readonly name="quantity" id="quantity" :value="qty"
                                    class="flex-1 w-12 text-center border-y border-gray-300 focus:ring-0 focus:outline-none py-1 mx-1 rounded-md">

                                <!-- Plus -->
                                <button type="button" @click="if(qty < maxQty) qty++" :disabled="qty >= maxQty"
                                    class="w-full py-1 bg-gray-200 hover:bg-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="material-icons text-lg">add</span>
                                </button>
                            </div>

                            {{-- <p class="mt-2 text-xs text-gray-500" x-text="`Available: ${maxQty}`"></p> --}}
                        </div>

                        <button type="submit"
                            class="px-6 py-2 text-white bg-black rounded-lg hover:bg-gray-800 transition flex items-center gap-3">
                            <span class="material-icons">add_shopping_cart</span>
                            {{ __('product.add_to_cart') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div>
            @if ($smiliarProducts->isNotEmpty())
                <h1 class="text-3xl montaga-regular m-6 mt-24">
                    {{ __('product.more_like_this') }}
                </h1>
            @endif

            <div class="flex gap-5 m-5">
                @foreach ($smiliarProducts as $product)
                    <div
                        class="overflow-hidden !transition bg-white rounded-lg shadow-md !duration-500 hover:shadow-2xl fade-up w-[250px]">
                        @if ($product->images->count())
                            <div
                                class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-1">
                                <img class="h-full object-contain cursor-zoom-in rounded-md" alt="{{ $product->name }}"
                                    src="{{ asset('storage/' . $product->images->first()->image_path) }}"
                                    @click="showModal = true; modalImage = '{{ asset('storage/' . $product->images->first()->image_path) }}'">

                            </div>
                        @endif

                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-charcoal">
                                {{ app()->getLocale() === 'ar' && $product->name_ar ? $product->name_ar : $product->name }}
                            </h3>
                            <p
                                class="mt-1 text-gray-600 w-min"{{ Str::limit(app()->getLocale() === 'ar' && $product->description_ar ? $product->description_ar : $product->description, 80) }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xl font-bold text-gray-600">
                                    {{ __('product.currency_aed') }} {{ number_format($product->price, 2) }}
                                </span>
                                <a href="{{ route('products.show', $product->id) }}"
                                    class="inline-block px-3 py-1 text-white transition bg-gray-600 rounded hover:bg-gray-700">
                                    {{ __('product.view') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div x-show="showModal" x-transition x-cloak
            class="fixed inset-0 bg-black bg-opacity-80 z-50 flex items-center justify-center p-4"
            @click.away="showModal = false" @keydown.escape.window="showModal = false" role="dialog"
            :aria-label="`{{ __('shop.image_preview') }}`">
            <div class="relative max-w-full max-h-screen">
                <button @click="showModal = false"
                    class="absolute -top-4 -right-4 flex justify-center items-center bg-white text-black text-2xl w-8 h-8 rounded-full shadow hover:bg-gray-100 z-50"
                    aria-label="{{ __('shop.close') }}">
                    <span class="material-icons">close</span>
                </button>
                <img :src="modalImage" class="max-w-full max-h-[90vh] rounded shadow-xl"
                    :alt="`{{ __('shop.image_preview') }}`">
            </div>
        </div>
    </div>



    <script>
        function productPage(init) {
            return {
                // data in
                images: Array.isArray(init.images) ? init.images : [],
                colors: Array.isArray(init.colors) ? init.colors : [],
                sizes: Array.isArray(init.sizes) ? init.sizes : [],

                // selections
                selectedColorHex: null,
                selectedSize: null,

                // carousel state
                index: 0,
                paused: false,
                hover: false,
                zoom: 2,
                originX: 50,
                originY: 50,
                intervalId: null,

                // computed: images to show based on selected color
                get displayImages() {
                    if (!this.selectedColorHex) return this.images;
                    const filtered = this.images.filter(img => (img.colorHex || null) === this.selectedColorHex);
                    return filtered.length ? filtered : this.images;
                },

                initSelections() {
                    // auto-select color if only one
                    if (this.colors.length === 1) {
                        this.selectedColorHex = this.colors[0].hex;
                    }
                    // auto-select size if only one
                    if (this.sizes.length === 1) {
                        this.selectedSize = this.sizes[0];
                    }
                    // start carousel
                    this.start();
                },

                selectColor(hex) {
                    this.selectedColorHex = hex;
                    // reset slide when color changes
                    this.index = 0;
                },

                selectSize(size) {
                    this.selectedSize = size;
                },

                // carousel controls (work with displayImages)
                start() {
                    this.stop();
                    if (this.displayImages.length <= 1) return;
                    this.intervalId = setInterval(() => {
                        if (!this.paused) this.next();
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
                    const rect = e.currentTarget.getBoundingClientRect();
                    const x = ((e.clientX - rect.left) / rect.width) * 100;
                    const y = ((e.clientY - rect.top) / rect.height) * 100;
                    this.originX = Math.max(0, Math.min(100, x));
                    this.originY = Math.max(0, Math.min(100, y));
                },
                imgStyle(i) {
                    const active = this.index === i;
                    const scale = this.hover && active ? this.zoom : 1;
                    const origin = `${this.originX}% ${this.originY}%`;
                    return `transform-origin:${origin}; transform:scale(${scale}); transition: transform 120ms ease;`;
                },
            }
        }
    </script>
@endsection
