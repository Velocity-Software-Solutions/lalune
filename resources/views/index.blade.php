@extends('layouts.app')

@push('head')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="h-[30vh] md:h-[60vh] mx-6 rounded-3xl 
             bg-cover bg-top bg-no-repeat md:bg-fixed"
        style="background-image:url('{{ asset('images/index-hero.jpg') }}');">
        <!-- Optional overlay -->
        <div
            class="w-full h-full bg-black/30 flex flex-col justify-evenly rounded-3xl backdrop:blur-sm px-8 py-4 text-center">
            <div class="self-center">
                <h1 class="text-2xl md:text-5xl font-bolder font-serif text-white/90">Made in Canada</h1>
                <br>
                <h3 class="text-xl md:text-4xl font-bold font-serif text-white/90">With <span
                        style="font-family:'Dancing Script', cursive;">love & care</span></h3>
            </div>
            <!-- Promo Codes Section -->
            <div class="space-y-2 text-white/85 text-xs md:text-base montserrat-regular self-center">
                <p>Free Shipping on CAD 250+ with code <span class="text-white font-semibold">FREESHIP</span></p>
                <div class="flex space-x-2 lg:space-x-6">
                    <!--<p>Save 10% on CAD 300+ with code <span class="text-white font-semibold">THANKS10</span></p>-->
                    <!--<p>Save 15% on CAD 400+ with code <span class="text-white font-semibold">THANKS15</span></p>-->
                    <!--<p>Save 20% on CAD 500+ with code <span class="text-white font-semibold">THANKS20</span></p>-->
                </div>
            </div>
        </div>
    </div>

    @php
        // Flatten your grouped products to a simple array
        $productsFlat = collect($products)
            ->flatMap(function ($group, $label) {
                return collect($group)->map(function ($p) use ($label) {
                    // Use the loaded relation if available (avoid N+1)
                    $approved = $p->relationLoaded('approvedReviews') ? $p->approvedReviews : collect();

                    return [
                        'id' => $p->id,
                        'slug' => $p->slug,
                        'name' => $p->name,
                        'description' => $p->description,
                        'price' => (float) $p->price,
                        'discount_price' => $p->discount_price ? (float) $p->discount_price : null,
                        'images' => $p->images->take(3)->map(fn($i) => ['path' => $i->image_path])->values(),
                        'category_label' => $label,
                        'category_id' => $p->category_id,

                        // sizes for filter
                        'sizes' => $p->sizes?->pluck('size')->filter()->values()->all() ?? [],

                        // ⭐️ NEW: rating summary
                        'avg_rating' => $approved->count() ? round($approved->avg('rating') * 2) / 2 : 0,
                        'reviews_count' => $approved->count(),
                    ];
                });
            })
            ->values();

        // Category dropdown options as { id, label }
        $categoryOptions = collect($products)
            ->map(
                fn($group, $label) => [
                    'id' => optional($group->first())->category_id,
                    'label' => $label,
                ],
            )
            ->filter(fn($c) => !is_null($c['id']))
            ->values();

        // NEW: Size options (unique + sorted, e.g., ["XS","S","M","L","XL"])
        $sizeOptions = collect($productsFlat)
            ->pluck('sizes') // [[...], [...], ...]
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();
    @endphp

    <div x-data="page(
        @js($productsFlat),
        @js($categoryOptions),
        @js(asset('storage')),
        @js($sizeOptions)
    )" class="relative min-h-screen bg-gray-50">

        <!-- Fixed Filter button (bottom-right) -->
        <div class="flex justify-evenly gap-4 fixed bottom-4 right-4 z-40">
            <button @click="showModal = true; modalImage = '{{ asset('images/size-chart.jpeg') }}'"
                class="px-5 py-3 rounded-full shadow-lg
                 bg-gradient-to-r from-black via-neutral-700 to-black
                 text-white font-medium
                 bg-[length:200%_100%] bg-left hover:bg-right transition-all duration-500">
                Size Chart
            </button>
            <button @click="drawerOpen = true"
                class="px-5 py-3 rounded-full shadow-lg
                 bg-gradient-to-r from-black via-neutral-700 to-black
                 text-white font-medium
                 bg-[length:200%_100%] bg-left hover:bg-right transition-all duration-500">
                Filter
            </button>
        </div>


        <!-- Slide-in Filter Drawer (left) -->
        <aside x-cloak x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-40">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50" @click="drawerOpen=false"></div>

            <!-- Panel -->
            <div x-transition:enter="transition transform ease-out duration-300"
                x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="absolute left-0 top-0 h-full w-80 max-w-[85vw] bg-white shadow-2xl p-5 overflow-y-auto">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold">Filters</h3>
                    <button @click="drawerOpen=false" class="text-2xl leading-none">&times;</button>
                </div>

                <!-- Search -->
                <label class="block text-sm font-medium text-gray-700">Search</label>
                <input type="text" x-model="q"
                    class="mt-1 mb-4 w-full rounded-lg border-gray-300 focus:border-black focus:ring-black"
                    placeholder="Search products…">

                <!-- Category -->
                <label class="block text-sm font-medium text-gray-700">Category</label>
                <select x-model="category"
                    class="mt-1 mb-4 w-full rounded-lg border-gray-300 focus:border-black focus:ring-black">
                    <option value="">All</option>
                    <template x-for="c in categories" :key="c.id">
                        <option :value="String(c.id)" x-text="c.label"></option>
                    </template>
                </select>
                <!-- Size (multi-select) -->
                <label class="block text-sm font-medium text-gray-700">Size</label>
                <div class="mt-2 mb-4 grid grid-cols-5 gap-2">
                    <template x-for="s in sizesAll" :key="s">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded border-gray-300 text-black focus:ring-black"
                                :value="s" x-model="sizesSelected">
                            <span x-text="s"></span>
                        </label>
                    </template>
                    <template x-if="sizesAll.length === 0">
                        <span class="col-span-3 text-xs text-gray-500">No sizes available</span>
                    </template>
                </div>

                <!-- Sort -->
                <label class="block text-sm font-medium text-gray-700">Sort by</label>
                <select x-model="sort"
                    class="mt-1 mb-6 w-full rounded-lg border-gray-300 focus:border-black focus:ring-black">
                    <option value="latest">Latest</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="discount">Top Discounts</option>
                </select>

                <div class="flex gap-2">
                    <button @click="reset()"
                        class="flex-1 inline-flex items-center justify-center rounded-lg border px-4 py-2 bg-black text-white hover:bg-neutral-800 transition">
                        Reset
                    </button>
                </div>
            </div>
        </aside>

        <!-- Content -->
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8 py-8">

            <!-- Animated toast -->
            <div x-show="justFiltered" x-transition class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-2 text-sm">
                Filters applied.
            </div>

            <!-- Grouped sections -->
            <template x-for="section in grouped" :key="section.id ?? section.label">
                <div class="mb-10">
                    <div class="flex items-center justify-between mb-3 sm:mb-4 mt-4">
                        <h3 class="text-2xl sm:text-3xl font-bold text-black" x-text="section.label"></h3>
                        <a :href="viewAllUrl(section.id)" class="text-sm text-gray-700 hover:text-black">View all →</a>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3 border-b border-black/10 pb-8">
                        <template x-for="item in section.items" :key="item.id">
                            <div x-transition
                                class="product-box flex flex-col justify-between px-2 py-5 transition rounded-2xl duration-500 hover:shadow-2xl hover:-translate-y-2 hover:border-black/5 bg-white">
                                <div>
                                    <template x-if="item.images && item.images.length">
                                        <div x-data="{ current: 0 }"
                                            class="relative flex justify-center items-center w-full h-[300px] overflow-hidden rounded-t-md p-2 bg-white">

                                            <!-- Main image -->
                                            <template x-for="(img, index) in item.images" :key="index">
                                                <img x-show="current === index" x-transition
                                                    :src="`${storageBase}/${img.path}`"
                                                    @click="showModal = true; modalImage = `${storageBase}/${img.path}`"
                                                    class="h-full object-contain cursor-zoom-in rounded-md"
                                                    :alt="item.name">
                                            </template>
                                            <!-- Prev Arrow -->
                                            <button
                                                @click="current = (current - 1 + item.images.length) % item.images.length"
                                                class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full w-8 h-8 flex justify-center items-center">
                                                &#10094;
                                            </button>

                                            <!-- Next Arrow -->
                                            <button @click="current = (current + 1) % item.images.length"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full w-8 h-8 flex justify-center items-center">
                                                &#10095;
                                            </button>
                                            <!-- Thumbnail squares (bottom center) -->
                                            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2">
                                                <template x-for="(thumb, tIndex) in item.images" :key="`thumb-${tIndex}`">
                                                    <button type="button" @click="current = tIndex"
                                                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-[6px] overflow-hidden border-2 transition
                     shadow-sm"
                                                        :class="current === tIndex ?
                                                            'border-white ring-2 ring-black/20' :
                                                            'border-white/80 opacity-80 hover:opacity-100'">
                                                        <img :src="`${storageBase}/${thumb.path}`" alt=""
                                                            class="w-full h-full object-cover">
                                                    </button>
                                                </template>
                                            </div>
                                        </div>


                                    </template>

                                    <div class="p-3 sm:p-4">
                                        <h3 class="text-base sm:text-lg montserrat-bold text-charcoal tracking-wide"
                                            x-text="item.name"></h3>

                                        {{-- Numeric rating chip --}}
                                        <div class="mt-1">
                                            <template x-if="item.reviews_count > 0">
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs sm:text-sm text-gray-800"
                                                    :aria-label="`${item.avg_rating.toFixed(1)} out of 5 based on ${item.reviews_count} reviews`">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"
                                                        aria-hidden="true">
                                                        <path
                                                            d="M12 17.3l-6.16 3.3 1.18-6.88-5-4.86 6.91-1 3.09-6.26 3.09 6.26 6.91 1-5 4.86 1.18 6.88z" />
                                                    </svg>
                                                    <span x-text="item.avg_rating.toFixed(1)"></span>
                                                    <span class="text-gray-500"
                                                        x-text="`/ 5 · ${item.reviews_count}`"></span>
                                                </span>
                                            </template>

                                            <template x-if="item.reviews_count === 0">
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs sm:text-sm text-gray-500">
                                                    New · No reviews yet
                                                </span>
                                            </template>
                                        </div>

                                        <p class="mt-1 text-sm sm:text-[15px] text-charcoal/90 font-mono line-clamp-3"
                                            x-html="item.description">
                                        </p>

                                        <div class="flex items-center justify-between mt-3">
                                            <template x-if="item.discount_price">
                                                <div class="flex items-center gap-2 sm:gap-3">
                                                    <span class="text-lg sm:text-xl font-semibold text-red-600"
                                                        x-text="`CAD ${formatPrice(item.discount_price)}`"></span>
                                                    <span class="hidden sm:inline text-gray-500 line-through"
                                                        x-text="`CAD ${formatPrice(item.price)}`"></span>
                                                    <span
                                                        class="text-[10px] px-1.5 py-0.5 font-bold text-white bg-green-600 rounded-md"
                                                        x-text="`-${discountPercent(item)}%`"></span>
                                                </div>
                                            </template>
                                            <template x-if="!item.discount_price">
                                                <span class="text-lg sm:text-2xl font-semibold text-black"
                                                    x-text="`CAD ${formatPrice(item.price)}`"></span>
                                            </template>
                                        </div>

                                    </div>
                                </div>

                                <a :href="productUrl(item.slug)"
                                    class="mt-2 inline-block text-center px-3 py-2 text-white rounded
                        transition-all duration-500 ease-in-out
                        bg-gradient-to-r from-black via-neutral-600 to-black
                        bg-[length:200%_100%] bg-right hover:bg-left">
                                    View
                                </a>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div x-show="grouped.length === 0" class="py-20 text-center text-gray-600">
                No products found.
            </div>
        </div>

        <!-- Image Zoom Modal -->
        <div x-cloak x-show="showModal" x-transition
            class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="showModal = false" role="dialog">
            <div class="relative max-w-full max-h-screen">
                <img @click.outside="showModal = false" :src="modalImage"
                    class="max-w-full max-h-[90vh] rounded shadow-xl" alt="">
            </div>
        </div>
    </div>
    {{-- Email subscribe popup (bottom-left) --}}
    {{-- Email subscribe popup (bottom-left) --}}
    <div x-data="{
        open: !localStorage.getItem('email_popup_closed'),
        email: '',
        close() {
            this.open = false;
            localStorage.setItem('email_popup_closed', '1');
        }
    }" x-show="open" x-transition x-cloak
        class="fixed bottom-4 left-4 z-50 w-[92vw] max-w-xs sm:max-w-sm">
        <div
            class="relative p-4 sm:p-5 rounded-2xl shadow-2xl
               bg-black/60 border border-white/30
               backdrop-blur-xl
               text-white/90">
            <!-- Close button -->
            <button type="button" @click="close()"
                class="absolute top-2 right-2 w-7 h-7 rounded-full
                   bg-black/50 hover:bg-black/70
                   flex items-center justify-center text-xs">
                ✕
            </button>

            <h3 class="text-sm sm:text-base font-semibold mb-1">
                Get exclusive updates & promo codes
            </h3>
            <p class="text-[11px] sm:text-xs mb-3 text-white/80">
                Join our list for early access to new collections, special offers,
                and members-only promo codes.
            </p>

            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="space-y-2">
                @csrf
                <input type="email" name="email" x-model="email" required placeholder="Enter your email"
                    class="w-full px-3 py-2 rounded-xl text-xs sm:text-sm
                       bg-white/10 border border-white/40
                       placeholder-white/70
                       focus:outline-none focus:ring-2 focus:ring-white/70
                       focus:border-transparent" />

                <button type="submit"
                    class="w-full px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold
                       bg-gradient-to-r from-black via-neutral-700 to-black
                       bg-[length:200%_100%] bg-left hover:bg-right
                       transition-all duration-500
                       shadow-lg shadow-black/40"
                    @click="if(email){ localStorage.setItem('email_popup_closed','1'); }">
                    Subscribe now
                </button>
            </form>

            <p class="mt-2 text-[10px] text-white/60 leading-snug">
                By subscribing, you agree to receive marketing emails from
                <span class="font-semibold">Lalune By NE</span>.
                You can unsubscribe at any time.
                {{-- Optionally: --}}
                {{-- See our <a href="{{ route('privacy') }}" class="underline">Privacy Policy</a>. --}}
            </p>
        </div>
    </div>

    <script>
        function page(products = [], categories = [], storageBase = '', sizeOptions = []) {
            return {
                // state
                drawerOpen: false,
                showModal: false,
                modalImage: '',
                storageBase,
                products,
                categories,
                sizesAll: sizeOptions, // NEW: all available sizes

                q: new URLSearchParams(location.search).get('q') || '',
                category: new URLSearchParams(location.search).get('category') || '',
                sort: new URLSearchParams(location.search).get('sort') || 'latest',

                // NEW: selected sizes from URL param (?sizes=M,L)
                sizesSelected: (() => {
                    const raw = new URLSearchParams(location.search).get('sizes') || '';
                    return raw ? raw.split(',').map(s => s.trim()).filter(Boolean) : [];
                })(),

                justFiltered: false,

                // computed
                get filtered() {
                    let list = [...this.products];

                    const q = this.q.trim().toLowerCase();
                    if (q) {
                        list = list.filter(p => {
                            const n = (p.name || '').toLowerCase();
                            const d = (p.description || '').toLowerCase();
                            return n.includes(q) || d.includes(q);
                        });
                    }

                    if (this.category) {
                        list = list.filter(p => String(p.category_id) === String(this.category));
                    }

                    // NEW: size filtering (keep products that have ANY selected size)
                    if (this.sizesSelected.length > 0) {
                        const set = new Set(this.sizesSelected.map(s => s.toLowerCase()));
                        list = list.filter(p => (p.sizes || []).some(sz => set.has(String(sz).toLowerCase())));
                    }

                    switch (this.sort) {
                        case 'price_low':
                            list.sort((a, b) => (a.discount_price ?? a.price) - (b.discount_price ?? b.price));
                            break;
                        case 'price_high':
                            list.sort((a, b) => (b.discount_price ?? b.price) - (a.discount_price ?? a.price));
                            break;
                        case 'discount':
                            const disc = p => (p.price > 0 && p.discount_price != null) ? (p.price - p.discount_price) /
                                p.price : 0;
                            list.sort((a, b) => disc(b) - disc(a));
                            break;
                        case 'latest':
                        default:
                            break;
                    }

                    return list;
                },

                get grouped() {
                    const map = new Map();
                    for (const p of this.filtered) {
                        const label = p.category_label || 'Uncategorized';
                        const id = p.category_id ?? null;
                        if (!map.has(label)) map.set(label, {
                            label,
                            id,
                            items: []
                        });
                        map.get(label).items.push(p);
                    }
                    return Array.from(map.values());
                },

                // actions
                apply() {
                    this.justFiltered = true;
                    setTimeout(() => this.justFiltered = false, 1200);

                    // Sync URL (no reload)
                    const u = new URL(location);
                    this.q ? u.searchParams.set('q', this.q) : u.searchParams.delete('q');
                    this.category ? u.searchParams.set('category', this.category) : u.searchParams.delete('category');
                    this.sort ? u.searchParams.set('sort', this.sort) : u.searchParams.delete('sort');

                    // NEW: sizes -> comma-separated
                    if (this.sizesSelected.length) {
                        u.searchParams.set('sizes', this.sizesSelected.join(','));
                    } else {
                        u.searchParams.delete('sizes');
                    }

                    history.replaceState({}, '', u);
                },

                reset() {
                    this.q = '';
                    this.category = '';
                    this.sort = 'latest';
                    this.sizesSelected = []; // NEW
                    this.apply();
                },

                // helpers
                truncate(s, n) {
                    return !s ? '' : (s.length > n ? s.slice(0, n - 1) + '…' : s);
                },
                formatPrice(v) {
                    return Number(v ?? 0).toFixed(2);
                },
                discountPercent(p) {
                    if (!p.price || !p.discount_price) return 0;
                    return Math.round(((p.price - p.discount_price) / p.price) * 100);
                },
                productUrl(id) {
                    return `{{ route('products.show', 0) }}`.replace('/0', `/${id}`);
                },
                viewAllUrl(categoryId) {
                    const u = new URL(`{{ route('products.index') }}`);
                    if (categoryId) u.searchParams.set('category', String(categoryId));
                    return u.toString();
                },
                moonSeg(r, max = 5) {
                    r = Math.max(0, Math.min(max, Number(r) || 0));
                    const full = Math.floor(r);
                    const half = (r - full) >= 0.5 ? 1 : 0;
                    const empty = max - full - half;
                    return {
                        full,
                        half,
                        empty
                    };
                },

            }
        }
    </script>
@endsection
