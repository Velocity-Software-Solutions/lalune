@extends('layouts.app')

@section('content')

    <div x-data="{ showModal: false, modalImage: '' }" class="min-h-screen bg-gray-50">
<div class="h-[30vh] md:h-[60vh] mx-6 rounded-3xl 
            hero-bg bg-center bg-no-repeat md:bg-fixed"
     style="background-image:url('{{ asset('images/collections-hero.jpg') }}');">
            <!-- Optional overlay -->
            <div class="w-full h-full bg-black/30 flex items-center justify-center rounded-3xl">
                <h1 class="text-3xl md:text-6xl montserrat-bolder text-white/90">Our Collections</h1>
            </div>
        </div>
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            @foreach ($products as $collection => $groupedProducts)
                {{-- If you localize collection, replace $collection with a localized value (see note below) --}}
                <h3 class="mt-10 mb-6 text-4xl font-bold text-black pb-2 text-center font-(sans-serif:Montserrat) fade-up">
                    {{ $collection }}
                </h3>


                <div
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 pb-9 border-b border-b-black/20 border-solid">
                    @foreach ($groupedProducts as $product)
                        <div
                            class="product-box flex flex-col justify-between px-2 py-5 !transition rounded-2xl !duration-500 hover:shadow-2xl hover:-translate-y-4 hover:border-black/5 fade-up will-change:transform">
                            <div>
                                @if ($product->images->count())
                                    <div x-data="{ current: 0 }"
                                        class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-2">
                                        <template x-for="(img, index) in {{ $product->images->take(3)->toJson() }}"
                                            :key="index">
                                            <img x-show="current === index" x-transition :src="'/storage/' + img.image_path"
                                                @click="showModal = true; modalImage = '/storage/' + img.image_path"
                                                class="h-full object-contain cursor-zoom-in rounded-md"
                                                alt="{{ $product->name }}">
                                        </template>

                                        <div class="absolute bottom-3 left-1/2 transform -translate-x-1/2 flex gap-2">
                                            <template x-for="(img, index) in {{ $product->images->take(3)->toJson() }}"
                                                :key="index">
                                                <button @click="current = index"
                                                    :class="{ 'bg-black': current === index, 'bg-gray-300': current !== index }"
                                                    class="btn-view w-3 h-3 rounded-full"></button>
                                            </template>
                                        </div>
                                    </div>
                                @endif
                                <div class="p-4">
                                    <h3 class="text-lg montserrat-bold text-charcoal tracking-wide">
                                        {{ app()->getLocale() === 'ar' && $product->name_ar ? $product->name_ar : $product->name }}
                                    </h3>
                                    <p class="mt-1 text-charcoal/90 font-mono">
                                        {{ Str::limit(app()->getLocale() === 'ar' && $product->description_ar ? $product->description_ar : $product->description, 80) }}
                                    </p>
                                    <div class="flex items-center justify-between mt-2">
                                        @if ($product->discount_price)
                                            <!-- Discounted Price -->
                                            <div class="flex gap-3">
                                                <span class="text-lg font-semibold text-red-600">
                                                    CAD {{ number_format($product->discount_price, 2) }}
                                                </span>
                                                <div class="flex items-center space-x-2">
                                                    <!-- Discount Badge -->
                                                    @php
                                                        $discountPercent = round(
                                                            (($product->price - $product->discount_price) /
                                                                $product->price) *
                                                                100,
                                                        );
                                                    @endphp
                                                    <span
                                                        class="text-[10px] px-1.5 py-0.5 font-bold text-white bg-green-600 rounded-md">
                                                        -{{ $discountPercent }}%
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <!-- Normal Price -->
                                            <span class="text-lg font-semibold text-black">
                                                CAD {{ number_format($product->price, 2) }}
                                            </span>
                                        @endif


                                    </div>

                                </div>
                            </div>
                            <a href="{{ route('products.show', $product->id) }}"
                                class="inline-block text-center px-3 py-2 text-white transition-all bg-gradient-to-b from-black via-neutral-600 bg-top to-black hover:bg-bottom rounded bg-[length:100%_200%] duration-500 ease-in-out">
                                {{ __('shop.view') }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Zoom Modal --}}
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
        document.addEventListener('alpine:init', () => {
            Alpine.data('carousel', (images) => ({
                current: 0,
                images,
                next() {
                    this.current = (this.current + 1) % this.images.length
                },
                prev() {
                    this.current = (this.current - 1 + this.images.length) % this.images.length
                }
            }))
        });
    </script>
@endsection
