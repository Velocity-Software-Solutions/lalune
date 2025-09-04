@extends('layouts.app')

@section('content')
    <div class="min-h-screen py-3" x-data="{ showModal: false, modalImage: '' }">
        <div class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 place-items-center">
                {{-- Carousel --}}
                <div x-data="imageCarousel(@js(
    $product->images
        ->map(
            fn($img) => [
                'src' => asset('storage/' . $img->image_path),
                'alt' => $img->alt_text ?? __('product.image_alt'),
            ],
        )
        ->values(),
))" x-init="start()" class="relative w-full h-96 rounded-lg overflow-hidden"
                    @mouseenter="paused = true; hover = true"
                    @mouseleave="paused = false; hover = false; originX = 50; originY = 50">
                    <!-- Slides -->
                    <template x-for="(img, i) in images" :key="i">
                        <div x-show="index === i" x-transition.opacity.duration.300ms
                            class="absolute inset-0 flex items-center justify-center" @mousemove="onMove($event)">
                            <img :src="img.src" :alt="img.alt"
                                class="max-w-full max-h-full object-contain select-none pointer-events-none"
                                draggable="false" :style="imgStyle(i)">
                        </div>
                    </template>

                    <!-- Dots -->
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2">
                        <template x-for="(img, i) in images" :key="i">
                            <button @click="go(i)" class="w-2.5 h-2.5 rounded-full transition"
                                :class="i === index ? 'bg-charcoal' : 'bg-gray-300'"
                                :aria-label="`{{ __('product.go_to_slide') }} ${i+1}`">
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <h1 class="mb-2 text-3xl montaga-semibold text-charcoal">
                        {{ app()->getLocale() === 'ar' && $product->name_ar ? $product->name_ar : $product->name }}</h1>
                    <p class="mb-4 text-lg text-gray-600">
                        {{ __('product.currency_aed') }} {{ number_format($product->price, 2) }}
                    </p>

                    @if ($product->description)
                        <h2 class="mb-2 text-3xl font-bold text-charcoal">{{ __('product.description') }}</h2>
                        <p class="min-h-[2rem] w-full text-sm">
                            {{ app()->getLocale() === 'ar' && $product->description_ar ? $product->description_ar : $product->description }}
                        </p>
                    @endif

                    <form action="{{ route('cart.add', $product->id) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="quantity" class="block text-sm font-medium text-gray-700">
                                {{ __('product.quantity') }}
                            </label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1"
                                class="block w-24 mt-1 border-gray-300 rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500">
                        </div>
                        <button type="submit"
                            class="px-6 py-2 text-white transition duration-200 bg-black rounded-lg hover:bg-gray-700 flex items-center justify-center gap-4">
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
        function imageCarousel(images = []) {
            return {
                images,
                index: 0,
                paused: false,
                hover: false,
                zoom: 2,
                originX: 50,
                originY: 50,
                intervalId: null,

                start() {
                    if (this.images.length <= 1) return;
                    this.stop();
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
                    this.index = (this.index + 1) % this.images.length;
                },
                prev() {
                    this.index = (this.index - 1 + this.images.length) % this.images.length;
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
                }
            }
        }
    </script>
@endsection
