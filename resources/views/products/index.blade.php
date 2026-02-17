@extends('layouts.app')

@push('head')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <style>
        h1 {
            font-size: 2.25rem;
            font-weight: 700;
        }

        h2 {
            font-size: 1.875rem;
            font-weight: 600;
        }

        h3 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        h4 {
            font-size: 1.25rem;
            font-weight: 500;
        }

        h5 {
            font-size: 1rem;
            font-weight: 500;
        }

        h6 {
            font-size: .875rem;
            font-weight: 500;
        }
    </style>

    @php
        use Illuminate\Support\Facades\Storage;

        // ✅ Background (keep if you have it on this page; otherwise remove safely)
        $defaultBg = asset('images/index-hero.jpg');
        $heroBg =
            isset($indexHero) && $indexHero && $indexHero->background_image
                ? Storage::url($indexHero->background_image)
                : $defaultBg;

        /**
         * ✅ Attach rating summary + min price data to each product:
         * - Reviews: same "chip" UI as home (avg_rating + reviews_count)
         * - Price: uses prices relation min effective price (disc > 0 preferred) else fallback
         */
        $productsWithMeta = collect($products)->map(function ($p) {
            // ---- Reviews (same logic as home) ----
            $approved = $p->relationLoaded('approvedReviews') ? $p->approvedReviews : collect();
            $p->avg_rating = $approved->count() ? round($approved->avg('rating') * 2) / 2 : 0;
            $p->reviews_count = $approved->count();

            // ---- Min price (same behavior you already have) ----
            $min = null;

            if (method_exists($p, 'prices') && $p->relationLoaded('prices')) {
                $rows = collect($p->prices)
                    ->map(function ($row) {
                        $price = (float) ($row->price ?? 0);
                        $disc = (float) ($row->discounted_price ?? 0);

                        $effective = $disc > 0 ? $disc : ($price > 0 ? $price : null);
                        if ($effective === null) {
                            return null;
                        }

                        return [
                            'effective' => $effective,
                            'original' => $price > 0 ? $price : null,
                            'is_discount' => $disc > 0 && $price > 0 && $disc < $price,
                        ];
                    })
                    ->filter()
                    ->sortBy('effective');

                $min = $rows->first();
            }

            if (!$min) {
                $basePrice = (float) ($p->price ?? 0);
                $baseDisc = (float) ($p->discount_price ?? 0);

                if ($baseDisc > 0 && $basePrice > 0 && $baseDisc < $basePrice) {
                    $min = ['effective' => $baseDisc, 'original' => $basePrice, 'is_discount' => true];
                } else {
                    $min = ['effective' => $basePrice, 'original' => null, 'is_discount' => false];
                }
            }

            $p->min_price_data = $min;

            return $p;
        });
    @endphp

    {{-- Optional hero (remove if not needed) --}}
    <div class="h-[22vh] md:h-[32vh] mx-6 rounded-3xl bg-cover bg-top bg-no-repeat md:bg-fixed"
        style="background-image:url('{{ $heroBg }}');">
        <div
            class="w-full h-full bg-black/30 flex items-center justify-center rounded-3xl backdrop:blur-sm px-8 py-4 text-center">
            <h1 class="text-2xl md:text-4xl font-serif text-white/90">
                {{ $category->name }}
            </h1>
        </div>
    </div>

    <div x-data="{ showModal: false, modalImage: '' }" class="min-h-screen py-6 bg-gray-50">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8 fade-up">

            <h3 class="mb-6 text-5xl font-serif font-semibold text-black border-solid border-b border-gray-50/40 pb-2">
                {{ $category->name }}
            </h3>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 pb-9 border-b border-b-black/20 border-solid">
                @foreach ($productsWithMeta as $product)
                    @php
                        $min = $product->min_price_data ?? [
                            'effective' => (float) ($product->price ?? 0),
                            'original' => null,
                            'is_discount' => false,
                        ];

                        $minDiscountPercent = null;
                        if (!empty($min['is_discount']) && !empty($min['original']) && (float) $min['original'] > 0) {
                            $minDiscountPercent = (int) round(
                                (((float) $min['original'] - (float) $min['effective']) / (float) $min['original']) *
                                    100,
                            );
                        }
                    @endphp

                    <div
                        class="product-box flex flex-col justify-between px-2 py-5 !transition rounded-2xl !duration-500 shadow-md hover:shadow-2xl hover:-translate-y-2 hover:border-black/5 bg-white fade-up will-change: transform">

                        {{-- Images --}}
                        @if ($product->images->count())
                            <div x-data="{ current: 0 }"
                                class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-2 bg-white">

                                <template x-for="(img, index) in {{ $product->images->take(3)->toJson() }}"
                                    :key="index">
                                    <img x-show="current === index" x-transition :src="'/storage/' + img.image_path"
                                        @click="showModal = true; modalImage = '/storage/' + img.image_path"
                                        class="h-full object-contain cursor-zoom-in rounded-md" alt="{{ $product->name }}">
                                </template>

                                {{-- Dots --}}
                                <div class="absolute bottom-3 left-1/2 transform -translate-x-1/2 flex gap-2">
                                    <template x-for="(img, index) in {{ $product->images->take(3)->toJson() }}"
                                        :key="index">
                                        <button @click="current = index"
                                            :class="{ 'bg-black': current === index, 'bg-gray-300': current !== index }"
                                            class="w-3 h-3 rounded-full"></button>
                                    </template>
                                </div>
                            </div>
                        @endif

                        {{-- Content --}}
                        <div class="p-4">
                            <h3 class="text-lg montserrat-bold text-charcoal tracking-wide">
                                {{ app()->getLocale() === 'ar' && $product->name_ar ? $product->name_ar : $product->name }}
                            </h3>

                            {{-- ⭐ Reviews chip (same style as home) --}}
                            <div class="mt-1">
                                @if (($product->reviews_count ?? 0) > 0)
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs sm:text-sm text-gray-800"
                                        aria-label="{{ number_format((float) ($product->avg_rating ?? 0), 1) }} out of 5 based on {{ $product->reviews_count }} reviews">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path
                                                d="M12 17.3l-6.16 3.3 1.18-6.88-5-4.86 6.91-1 3.09-6.26 3.09 6.26 6.91 1-5 4.86 1.18 6.88z" />
                                        </svg>
                                        <span>{{ number_format((float) ($product->avg_rating ?? 0), 1) }}</span>
                                        <span class="text-gray-500">/ 5 · {{ $product->reviews_count }}</span>
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs sm:text-sm text-gray-500">
                                        New · No reviews yet
                                    </span>
                                @endif
                            </div>

                            @php
                                $desc =
                                    app()->getLocale() === 'ar' && $product->description_ar
                                        ? $product->description_ar
                                        : $product->description;
                            @endphp

                            <p class="mt-1 text-charcoal/90 font-sans leading-snug tracking-normal break-words hyphens-auto clamp-3"
                                dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
                                {{ \Illuminate\Support\Str::limit(strip_tags($desc), 140) }}
                            </p>

                            {{-- Price --}}
                            <div class="flex items-center justify-between mt-3">
                                @if (!empty($min['is_discount']) && !empty($min['original']) && (float) $min['effective'] > 0)
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <span class="text-lg sm:text-xl font-semibold text-red-600">
                                            CAD {{ number_format((float) $min['effective'], 2) }}
                                        </span>
                                        <span class="hidden sm:inline text-gray-500 line-through">
                                            CAD {{ number_format((float) $min['original'], 2) }}
                                        </span>
                                        @if (!is_null($minDiscountPercent))
                                            <span
                                                class="text-[10px] px-1.5 py-0.5 font-bold text-white bg-green-600 rounded-md">
                                                -{{ $minDiscountPercent }}%
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-lg sm:text-2xl font-semibold text-black">
                                        CAD {{ number_format((float) ($min['effective'] ?? $product->price), 2) }}
                                    </span>
                                @endif

                                <a href="{{ route('products.show', $product->id) }}"
                                    class="inline-block px-3 py-1 text-white transition bg-black hover:bg-black/90 focus:ring-2 focus:ring-gold/50 rounded">
                                    {{ __('shop.view') }}
                                </a>
                            </div>

                            <p class="text-sm text-gray-500">Price may vary by selected options.</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Zoom Modal --}}
        <div x-show="showModal" x-transition x-cloak
            class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click.away="showModal = false"
            @keydown.escape.window="showModal = false" role="dialog" :aria-label="`{{ __('shop.image_preview') }}`">
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
@endsection
