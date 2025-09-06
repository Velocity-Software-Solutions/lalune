@extends('layouts.app')

@push('head')
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
@php
  // Flatten your grouped $products (['Category' => Collection<Product>]) to a simple array
  $productsFlat = collect($products)->flatMap(function ($group, $label) {
      return collect($group)->map(function ($p) use ($label) {
          return [
              'id' => $p->id,
              'name' => $p->name,
              'description' => $p->description,
              'price' => (float) $p->price,
              'discount_price' => $p->discount_price ? (float) $p->discount_price : null,
              'images' => $p->images->take(3)->map(fn($i) => ['path' => $i->image_path])->values(),
              'category_label' => $label,
              'category_slug' => \Illuminate\Support\Str::slug($label),
          ];
      });
  })->values();

  // Build category dropdown options from the same grouping
  $categoryOptions = collect(array_keys($products->toArray()))
      ->map(fn($label) => ['slug' => \Illuminate\Support\Str::slug($label), 'label' => $label])
      ->values();
@endphp

<div x-data="page(
        @js($productsFlat),
        @js($categoryOptions),
        @js(asset('storage'))
     )"
     class="relative min-h-screen bg-gray-50">

  <!-- Fixed Filter button (bottom-right) -->
  <button @click="drawerOpen = true"
          class="fixed bottom-4 right-4 z-40 px-5 py-3 rounded-full shadow-lg
                 bg-gradient-to-r from-black via-neutral-700 to-black
                 text-white font-medium
                 bg-[length:200%_100%] bg-left hover:bg-right transition-all duration-500">
      Filter
  </button>

  <!-- Slide-in Filter Drawer (left) -->
  <aside x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-40">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50" @click="drawerOpen=false"></div>

    <!-- Panel -->
    <div x-transition:enter="transition transform ease-out duration-300"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200"
         x-transition:leave-start="translate-x-0"
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
        <template x-for="c in categories" :key="c.slug">
          <option :value="c.slug" x-text="c.label"></option>
        </template>
      </select>

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
    <div x-show="justFiltered" x-transition
         class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-2 text-sm">
      Filters applied.
    </div>

    <!-- Grouped sections -->
    <template x-for="section in grouped" :key="section.label">
      <div class="mb-10">
        <div class="flex items-center justify-between mb-3 sm:mb-4 mt-4">
          <h3 class="text-2xl sm:text-3xl font-bold text-black" x-text="section.label"></h3>
          <a :href="viewAllUrl(section.slug)" class="text-sm text-gray-700 hover:text-black">View all →</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3 border-b border-black/10 pb-8">
          <template x-for="item in section.items" :key="item.id">
            <div x-transition
                 class="product-box flex flex-col justify-between px-2 py-5 transition rounded-2xl duration-500 hover:shadow-2xl hover:-translate-y-2 hover:border-black/5 bg-white">
              <div>
                <template x-if="item.images && item.images.length">
                  <div x-data="{ current: 0 }"
                       class="relative flex justify-center items-center w-full h-[260px] sm:h-[300px] overflow-hidden rounded-t-md p-2 bg-white">
                    <template x-for="(img, index) in item.images" :key="index">
                      <img x-show="current === index" x-transition
                           :src="`${storageBase}/${img.path}`"
                           @click="$root.showModal = true; $root.modalImage = `${storageBase}/${img.path}`"
                           class="h-full object-contain cursor-zoom-in rounded-md"
                           :alt="item.name">
                    </template>

                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2">
                      <template x-for="(img, index) in item.images" :key="index">
                        <button @click="current = index"
                                :class="current === index ? 'bg-black' : 'bg-gray-300'"
                                class="w-2.5 h-2.5 rounded-full"
                                aria-label="Show image"></button>
                      </template>
                    </div>
                  </div>
                </template>

                <div class="p-3 sm:p-4">
                  <h3 class="text-base sm:text-lg montserrat-bold text-charcoal tracking-wide"
                      x-text="item.name"></h3>

                  <p class="mt-1 text-sm sm:text-[15px] text-charcoal/90 font-mono"
                     x-text="truncate(item.description, 90)"></p>

                  <div class="flex items-center justify-between mt-3">
                    <template x-if="item.discount_price">
                      <div class="flex items-center gap-2 sm:gap-3">
                        <span class="text-lg sm:text-xl font-semibold text-red-600"
                              x-text="`CAD ${formatPrice(item.discount_price)}`"></span>
                        <span class="hidden sm:inline text-gray-500 line-through"
                              x-text="`CAD ${formatPrice(item.price)}`"></span>
                        <span class="text-[10px] px-1.5 py-0.5 font-bold text-white bg-green-600 rounded-md"
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

              <a :href="productUrl(item.id)"
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
  <div x-show="showModal" x-transition
       class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
       @click.away="showModal = false" @keydown.escape.window="showModal = false" role="dialog">
    <div class="relative max-w-full max-h-screen">
      <button @click="showModal = false"
              class="absolute -top-4 -right-4 flex justify-center items-center bg-white text-black text-2xl w-8 h-8 rounded-full shadow hover:bg-gray-100 z-50"
              aria-label="Close">
        ×
      </button>
      <img :src="modalImage" class="max-w-full max-h-[90vh] rounded shadow-xl" alt="">
    </div>
  </div>
</div>

<script>
function page(products = [], categories = [], storageBase = ''){
  return {
    // state
    drawerOpen: false,
    showModal: false,
    modalImage: '',
    storageBase,
    products,
    categories,
    q: new URLSearchParams(location.search).get('q') || '',
    category: new URLSearchParams(location.search).get('category') || '',
    sort: new URLSearchParams(location.search).get('sort') || 'latest',
    justFiltered: false,

    // computed
    get filtered(){
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
        list = list.filter(p => p.category_slug === this.category);
      }

      switch (this.sort) {
        case 'price_low':
          list.sort((a,b) => (a.discount_price ?? a.price) - (b.discount_price ?? b.price));
          break;
        case 'price_high':
          list.sort((a,b) => (b.discount_price ?? b.price) - (a.discount_price ?? a.price));
          break;
        case 'discount':
          const disc = p => (p.price>0 && p.discount_price!=null) ? (p.price - p.discount_price)/p.price : 0;
          list.sort((a,b) => disc(b) - disc(a));
          break;
        case 'latest':
        default:
          // keep order as provided
          break;
      }

      return list;
    },

    get grouped(){
      const map = new Map();
      for (const p of this.filtered) {
        const key = p.category_label || 'Uncategorized';
        const slug = p.category_slug || 'uncategorized';
        if (!map.has(key)) map.set(key, { label: key, slug, items: [] });
        map.get(key).items.push(p);
      }
      return Array.from(map.values());
    },

    // actions
    apply(){
      this.justFiltered = true;
      setTimeout(() => this.justFiltered = false, 1200);
      // Sync URL (no reload)
      const u = new URL(location);
      this.q ? u.searchParams.set('q', this.q) : u.searchParams.delete('q');
      this.category ? u.searchParams.set('category', this.category) : u.searchParams.delete('category');
      this.sort ? u.searchParams.set('sort', this.sort) : u.searchParams.delete('sort');
      history.replaceState({}, '', u);
    },
    reset(){
      this.q = '';
      this.category = '';
      this.sort = 'latest';
      this.apply();
    },

    // helpers
    truncate(s, n){ return !s ? '' : (s.length > n ? s.slice(0, n - 1) + '…' : s); },
    formatPrice(v){ return Number(v ?? 0).toFixed(2); },
    discountPercent(p){
      if (!p.price || !p.discount_price) return 0;
      return Math.round(((p.price - p.discount_price) / p.price) * 100);
    },
    productUrl(id){
      // Uses your named route products.show
      return `{{ route('products.show', 0) }}`.replace('/0', `/${id}`);
    },
    viewAllUrl(slug){
      const u = new URL(`{{ route('products.index') }}`);
      if (slug) u.searchParams.set('category', slug);
      return u.toString();
    }
  }
}
</script>
@endsection
