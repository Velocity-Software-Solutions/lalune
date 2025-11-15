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
                // if you have $img->id, pass it too (optional but nice)
                'src' => asset('storage/' . $img->image_path),
                'alt' => $img->alt_text ?? __('product.image_alt'),
                'colorHex' => $img->color_code ? strtoupper($img->color_code) : null, // null means "works for all colors"
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
]))" <div class="grid grid-cols-1 gap-16 lg:gap-8 md:grid-cols-2 place-items-center">
            {{-- Left Panel --}}
            <div class="w-full h-full">
                {{-- Carousel --}}
                <div class="relative w-full h-full rounded-lg overflow-hidden min-h-96 flex justify-center items-center">
                    <div>
                        <!-- Slides -->
                        <template x-for="(img, i) in displayImages" :key="i">
                            <div x-show="index === i" x-transition.opacity.duration.300ms
                                class="flex items-center justify-center w-fit m-5" @mouseenter="paused = true; hover = true"
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
            <div class="w-full px-5">
                <h1 class="mb-2 text-3xl montaga-semibold text-charcoal">
                    {{ $product->name }}
                </h1>

                <p class="mb-4 text-lg text-gray-600">
                    {{ __('product.currency_aed') }} {{ number_format($product->price, 2) }}
                </p>

                @if ($product->description)
                    <h2 class="mb-2 text-2xl font-bold text-charcoal">
                        {{ __('product.description') }}</h2>
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
                                    class="w-full text-sm rounded-md border-gray-300 focus:border-black focus:ring-black">
                                @error('author_name')
                                    <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-700 mb-1">Email (not published)</label>
                                <input type="email" name="author_email"
                                    value="{{ old('author_email', optional(auth()->user())->email) }}"
                                    class="w-full text-sm rounded-md border-gray-300 focus:border-black focus:ring-black">
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
                    if (!this.selectedColorHex) {
                        return this.images; // no selection → show all (no duplicates)
                    }
                    // selection → show matching color + no-color images
                    return this.images.filter(img =>
                        (img.colorHex || null) === this.selectedColorHex || img.colorHex == null
                    );
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
