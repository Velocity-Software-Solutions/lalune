@extends('layouts.app')

@section('title', $product->name)

@push('head')
    <meta name="description" content="{{ Str::limit(strip_tags($product->description), 150) }}">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=shopping_bag_speed" />
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

        $colorHexById = $product->colors->pluck('color_code', 'id')->map(fn($v) => strtoupper($v));
        $sizeNameById = $product->sizes->pluck('size', 'id');

        $stockPayload = $product->stock
            ->map(function ($row) use ($colorHexById, $sizeNameById) {
                return [
                    'id' => $row->id,
                    'colorId' => $row->color_id,
                    'sizeId' => $row->size_id,
                    'colorHex' => $row->color_id ? $colorHexById[$row->color_id] ?? null : null,
                    'size' => $row->size_id ? $sizeNameById[$row->size_id] ?? null : null,
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
            $product->discount_price !== null && (float) $product->discount_price > 0
                ? (float) $product->discount_price
                : null;
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
        <div x-data="productPage(@js([
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
    'colors' => $product->colors->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => strtoupper($c->color_code)])->values(),
    'sizes' => $product->sizes->map(fn($s) => ['id' => $s->id, 'size' => $s->size])->values(),
    'stock' => $stockPayload,
    'prices' => $pricePayload,
    'productBasePrice' => (float) $product->price,
    'productBaseDiscount' => $baseDiscount,
    'productTotalQty' => $productTotalQty ?? 0,
]))" x-init="initSelections()">
            <div class="grid grid-cols-1 gap-16 lg:gap-8 md:grid-cols-2 place-items-center">
                <div class="w-full h-full">
                    <div
                        class="relative w-full h-full rounded-lg overflow-hidden min-h-96 flex justify-center items-center">
                        <div>
                            <template x-for="(img, i) in displayImages" :key="i">
                                <div x-show="index === i" x-transition.opacity.duration.300ms
                                    class="flex items-center justify-center w-fit m-5" @mouseenter="hover = true"
                                    @mouseleave="hover = false; originX = 50; originY = 50" @mousemove="onMove($event)">
                                    <img :src="img.src" :alt="img.alt"
                                        class="max-w-full max-h-full object-contain select-none pointer-events-none rounded"
                                        draggable="false" :style="imgStyle(i)">
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

                                <template
                                    x-if="!(priceView().discount !== null && priceView().discount < priceView().price)">
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
                                    <span
                                        x-text="`${formatMoney(priceView().min)} – ${formatMoney(priceView().max)}`"></span>
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
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2">{{ __('product.quantity') }}</label>

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
                        <div class="flex wrap gap-8">
                            <button type="submit" :disabled="!canAddToCart()"
                                class="relative px-6 py-2 text-white rounded-lg transition gap-3 overflow-hidden
                                       flex items-center justify-start"
                                :class="canAddToCart() ? 'bg-black group' : 'bg-gray-400 cursor-not-allowed'">
                                <span
                                    class="material-icons absolute left-6 transition-all duration-300 ease-in-out
                                           group-hover:left-1/2 group-hover:-translate-x-1/2">add_shopping_cart</span>
                                <span
                                    class="ml-8 transition-all duration-300 ease-in-out
                                           group-hover:translate-x-4 group-hover:opacity-0">
                                    {{ __('product.add_to_cart') }}
                                </span>
                            </button>
                            <button type="submit" :disabled="!canAddToCart()"
                                formaction="{{ route('cart.buy-now', ['id' => $product->id]) }}"
                                class="relative px-6 py-2 text-white rounded-lg transition overflow-hidden
                                       flex items-center justify-start"
                                :class="canAddToCart() ? 'bg-black group' : 'bg-gray-400 cursor-not-allowed'">

                                <!-- Icon -->
                                <span
                                    class="material-symbols-outlined absolute left-6 transition-all duration-300 ease-in-out
                                           group-hover:left-1/2 group-hover:-translate-x-1/2">
                                    shopping_bag_speed
                                </span>

                                <!-- Text -->
                                <span
                                    class="ml-8 transition-all duration-300 ease-in-out
                                           group-hover:translate-x-4 group-hover:opacity-0">
                                    Buy Now
                                </span>

                            </button>

                        </div>
                    </form>
                </div>
            </div>

            {{-- Compact Reviews Rail (last 3) + modal write form --}}
        @php
            use Illuminate\Support\Str;

            $approved = $product->relationLoaded('approvedReviews') ? $product->approvedReviews : collect();
            $recent = $approved->sortByDesc(fn($r) => $r->created_at ?? now())->take(5);
        @endphp

        <div x-data="{ showReviewForm: false, showFullReview: false, fullReview: { author: '', date: '', rating: 0, comment: '', image: '' } }" class="mt-16 m-4" id="reviews">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-black">Customer Reviews</h2>

                <div class="flex items-center gap-2">
                    <button type="button" @click="showReviewForm = true"
                        class="inline-flex items-center px-3 py-1.5 rounded-md bg-black text-white text-sm hover:bg-neutral-800">
                        Write a review
                    </button>
                </div>
            </div>

            {{-- slim horizontal rail --}}
            <div class="flex gap-3 overflow-x-auto pb-1 snap-x">
                @forelse($recent as $rev)
                    @php
                        $r = max(0, min(5, (float) ($rev->rating ?? 0)));
                        $full = (int) floor($r);
                        $half = $r - $full >= 0.5 ? 1 : 0;
                        $empty = 5 - $full - $half;

                        $author = $rev->author_name ?? ($rev->name ?? (optional($rev->user)->name ?? 'Anonymous'));

                        $img = $rev->image_path ? asset('storage/' . $rev->image_path) : null;
                    @endphp

                    <div
                        class="shrink-0 snap-start w-52 rounded-lg border border-black/10 bg-white p-2 flex flex-col justify-between items-center">
                        <div class="flex items-center gap-2">
                            {{-- tiny image --}}
                            @if ($img)
                                <button type="button" class="shrink-0"
                                    @click.prevent="showModal = true; modalImage = '{{ e($img) }}'">
                                    <img src="{{ $img }}" alt="Review image"
                                        class="w-10 h-10 object-cover rounded-md ring-1 ring-gray-200">
                                </button>
                            @endif

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-sm font-medium text-black truncate max-w-[8rem]">{{ $author }}</span>
                                    <span
                                        class="text-[11px] text-gray-500">{{ optional($rev->created_at)->diffForHumans() }}</span>
                                </div>

                                {{-- tiny squares rating --}}
                                <div class="mt-0.5 inline-flex items-center gap-0.5 text-amber-500"
                                    aria-label="{{ $r }} out of 5">
                                    @for ($i = 0; $i < $full; $i++)
                                        <svg class="w-3 h-3" viewBox="0 0 16 16" aria-hidden="true">
                                            <rect x="1" y="1" width="14" height="14" rx="2"
                                                fill="currentColor" />
                                        </svg>
                                    @endfor
                                    @if ($half)
                                        <svg class="w-3 h-3" viewBox="0 0 16 16" aria-hidden="true">
                                            <rect x="1" y="1" width="7" height="14" rx="2"
                                                fill="currentColor" />
                                            <rect x="1" y="1" width="14" height="14" rx="2"
                                                fill="none" stroke="currentColor" stroke-width="1.3" />
                                        </svg>
                                    @endif
                                    @for ($i = 0; $i < $empty; $i++)
                                        <svg class="w-3 h-3" viewBox="0 0 16 16" aria-hidden="true">
                                            <rect x="1" y="1" width="14" height="14" rx="2"
                                                fill="none" stroke="currentColor" stroke-width="1.3" />
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                        </div>
                        @if ($rev->comment)
                            <div class="mt-1 relative">
                                {{-- scrollable text --}}
                                <div class="max-h-24 overflow-y-auto pr-1 text-[12px] leading-snug text-gray-700">
                                    {{ $rev->comment }}
                                </div>

                                {{-- soft gradient fades (top/bottom) --}}
                                <div
                                    class="pointer-events-none absolute inset-x-0 bottom-0 h-4 bg-gradient-to-t from-white to-transparent">
                                </div>
                            </div>

                            {{-- read-full button (opens modal) --}}
                            @php
                                $author = $author ?? 'Anonymous';
                                $created = optional($rev->created_at)->diffForHumans();
                                $img = $rev->image_path ? asset('storage/' . $rev->image_path) : null;
                                $rating = (float) ($rev->rating ?? 0);
                            @endphp
                            <button type="button" class="mt-2 text-[12px] font-medium text-black/80 hover:underline"
                                @click="
            showFullReview = true;
            fullReview = {
                author: @js($author),
                date: @js($created),
                rating: {{ $rating }},
                comment: @js($rev->comment),
                image: @js($img)
            };
        ">
                                Read full review →
                            </button>
                        @endif

                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-black/10 bg-white p-4 text-center text-gray-600">
                        No reviews yet — be the first to review.
                    </div>
                @endforelse
            </div>

            {{-- ===== Modal: Write a review (full form) ===== --}}
            <div x-cloak x-show="showReviewForm" x-transition
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
                @keydown.escape.window="showReviewForm=false" role="dialog" aria-modal="true">
                <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-base font-semibold text-black">Write a review</h3>
                        <button class="text-2xl leading-none -mr-1" @click="showReviewForm=false">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('products.reviews.store', $product) }}"
                        enctype="multipart/form-data" x-data="{ rating: Number('{{ old('rating', 0) }}') || 0, hover: 0 }" class="space-y-4">
                        @csrf

                        {{-- name & email --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-700 mb-1">Your name</label>
                                <input type="text" name="author_name"
                                    value="{{ old('author_name', optional(auth()->user())->name) }}"
                                    class="w-full text-sm rounded-md border border-gray-300 focus:border-black focus:ring-black">
                                @error('author_name')
                                    <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-700 mb-1">Email (not published)</label>
                                <input type="email" name="author_email"
                                    value="{{ old('author_email', optional(auth()->user())->email) }}"
                                    class="w-full text-sm rounded-md border border-gray-300 focus:border-black focus:ring-black">
                                @error('author_email')
                                    <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- star picker --}}
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Rating</label>
                            <div class="inline-flex items-center gap-1 text-amber-500 select-none">
                                <template x-for="i in 5" :key="i">
                                    <button type="button" class="p-0.5" @mouseenter="hover=i" @mouseleave="hover=0"
                                        @click="rating=i" :aria-label="`Rate ${i} star${i>1?'s':''}`">
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21 12 17.27z"
                                                :fill="(hover || rating) >= i ? 'currentColor' : 'none'"
                                                :stroke="(hover || rating) >= i ? 'currentColor' : 'currentColor'"
                                                :stroke-width="(hover || rating) >= i ? 0 : 1.8" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                            <input type="hidden" name="rating" :value="rating">
                            @error('rating')
                                <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- image --}}
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Photo (optional)</label>
                            <input type="file" name="image" accept="image/*"
                                class="block w-full text-xs text-gray-700 file:mr-2 file:rounded-md file:border-0 file:bg-black file:px-2 file:py-1.5 file:text-white hover:file:bg-neutral-800">
                            <p class="mt-1 text-[11px] text-gray-500">JPG/PNG up to 4MB.</p>
                            @error('image')
                                <p class="text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- comment --}}
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Comment</label>
                            <textarea name="comment" rows="4"
                                class="w-full text-sm rounded-md border-gray-300 focus:border-black focus:ring-black"
                                placeholder="Share your thoughts…">{{ old('comment') }}</textarea>
                            @error('comment')
                                <p class="text-[11px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <button type="button" @click="showReviewForm=false"
                                class="px-3 py-2 rounded-md border text-sm">Cancel</button>
                            <button type="submit"
                                class="px-3 py-2 rounded-md bg-black text-white hover:bg-neutral-800 text-sm">
                                Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            {{-- ===== Modal: Read full review ===== --}}
            <div x-cloak x-show="showFullReview" x-transition
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
                @keydown.escape.window="showFullReview=false" role="dialog" aria-modal="true">
                <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl ">
                    <div class="flex items-center justify-between mb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-semibold text-black truncate"
                                    x-text="fullReview.author || 'Anonymous'"></h3>
                                <span class="text-[11px] text-gray-500" x-text="fullReview.date"></span>
                            </div>
                            {{-- stars --}}
                            <!-- Replace the whole stars section with this -->
                            <div class="mt-1 inline-flex items-center gap-0.5 text-amber-500"
                                :aria-label="`${fullReview.rating} out of 5`">
                                <template x-for="i in 5" :key="i">
                                    <svg class="w-4 h-4" viewBox="0 0 16 16" aria-hidden="true">
                                        <!-- outline square -->
                                        <rect x="1" y="1" width="14" height="14" rx="2" fill="none"
                                            stroke="currentColor" stroke-width="1.3"></rect>

                                        <!-- fill square (full or partial) -->
                                        <rect x="1" y="1"
                                            :width="(() => {
                                                const r = Number(fullReview.rating) || 0;
                                                const full = Math.floor(r);
                                                const hasHalf = (r - full) >= 0.5;
                                                if (i <= full) return 14; // full
                                                if (i === full + 1 && hasHalf) return 7; // half
                                                return 0; // empty
                                            })()"
                                            height="14" rx="2" fill="currentColor"></rect>
                                    </svg>
                                </template>
                            </div>

                        </div>
                        <button class="text-2xl leading-none -mr-1" @click="showFullReview=false">&times;</button>
                    </div>

                    <div class="space-y-3">
                        <template x-if="fullReview.image">
                            <img :src="fullReview.image" alt="Review image"
                                class="w-full h-52 object-contain rounded-md">
                        </template>

                        <div class="relative">
                            <div class="max-h-80 overflow-y-auto pr-2 text-sm text-gray-800 leading-relaxed"
                                x-text="fullReview.comment"></div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end">
                        <button class="px-3 py-2 rounded-md border text-sm" @click="showFullReview=false">Close</button>
                    </div>
                </div>
            </div>

        </div>


            <div>
                @if ($smiliarProducts->isNotEmpty())
                    <h1 class="text-3xl montaga-regular m-6 mt-14">
                        {{ __('product.more_like_this') }}
                    </h1>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3 border-b border-black/10 pb-8 mx-5">
                    @foreach ($smiliarProducts as $sp)
                        <div
                            class="product-box h-fit flex flex-col justify-between px-2 py-5 transition rounded-2xl duration-500 hover:shadow-2xl hover:-translate-y-2 hover:border-black/5 bg-white">
                            @if ($sp->images->count())
                                <div
                                    class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-1">
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
                                <div class="mt-1 text-gray-600 line-clamp-3">
                                    {!! $sp->description !!}
                                </div>
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
                productBaseDiscount: (init.productBaseDiscount === null || init.productBaseDiscount === undefined || Number(
                        init.productBaseDiscount) <= 0) ?
                    null : Number(init.productBaseDiscount),
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

                hasColorOptions() {
                    return this.colors.length > 0
                },
                hasSizeOptions() {
                    return this.sizes.length > 0
                },

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
                            price: (p.price !== null && p.price !== undefined && Number(p.price) > 0) ? Number(p
                                .price) : null,
                            discounted_price: (p.discounted_price !== null && p.discounted_price !== undefined &&
                                    Number(p.discounted_price) > 0) ?
                                Number(p.discounted_price) : null,
                        };
                    }
                    this.priceIndex = idx;
                },

                get displayImages() {
                    let imgs = this.images;

                    if (this.selectedColorHex) {
                        imgs = imgs.filter(img => (img.colorHex || null) === this.selectedColorHex || img.colorHex ==
                            null);
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
                    const row = this.stock.find(r => (r.colorHex || null) === (colorHex || null) && (r.size || null) === (
                        size || null));
                    return row ? Math.max(0, parseInt(row.qty, 10) || 0) : 0;
                },

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
                    const baseDiscount = (this.productBaseDiscount !== null && Number(this.productBaseDiscount) > 0) ?
                        Number(this.productBaseDiscount) :
                        null;

                    const row = this.priceRowFor(colorId, sizeId);

                    if (row) {
                        const price = (row.price !== null && Number(row.price) > 0) ? Number(row.price) : basePrice;
                        const discount = (row.discounted_price !== null && Number(row.discounted_price) > 0) ? Number(row
                            .discounted_price) : null;
                        const effective = (discount !== null && discount < price) ? discount : price;

                        return {
                            price,
                            discount,
                            effective,
                            hasVariant: true
                        };
                    }

                    const price = basePrice;
                    const discount = baseDiscount;
                    const effective = (discount !== null && discount < price) ? discount : price;

                    return {
                        price,
                        discount,
                        effective,
                        hasVariant: false
                    };
                },

                inStockVariantPairsFiltered() {
                    const pairs = this.stock
                        .filter(r => (Number(r.qty) || 0) > 0)
                        .map(r => ({
                            colorId: this.normId(r.colorId ?? null),
                            sizeId: this.normId(r.sizeId ?? null)
                        }));

                    const selectedColorId = this.currentColorId();
                    const selectedSizeId = this.currentSizeId();

                    return pairs.filter(p => {
                        if (this.hasColorOptions() && selectedColorId !== null && (p.colorId ?? null) !==
                            selectedColorId) return false;
                        if (this.hasSizeOptions() && selectedSizeId !== null && (p.sizeId ?? null) !==
                            selectedSizeId) return false;
                        return true;
                    });
                },

                rangeEffectivePrice() {
                    const pairs = this.inStockVariantPairsFiltered();
                    if (!pairs.length) return null;

                    let min = Infinity,
                        max = -Infinity;
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
                    return {
                        min,
                        max,
                        variantAware
                    };
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
                    const baseDiscount = (this.productBaseDiscount !== null && Number(this.productBaseDiscount) > 0) ?
                        Number(this.productBaseDiscount) :
                        null;

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
