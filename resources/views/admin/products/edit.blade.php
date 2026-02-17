@extends('layouts.admin')

@section('title', 'Edit Product')

@push('head')
    @vite(['resources/js/summernote.js'])
@endpush

@section('content')
@php
    /*
    |--------------------------------------------------------------------------
    | 1) BOOTSTRAP DATA (old() OR DB)
    |--------------------------------------------------------------------------
    | We "boot" sizes, colors, stock_matrix, price_matrix, images so:
    | - If validation fails → old() repopulates UI exactly as user entered
    | - Otherwise → load from $product relations
    */

    // ---------- Sizes ----------
    $bootSizes = old('sizes');
    if (!is_array($bootSizes)) {
        $bootSizes = $product->sizes->pluck('size')->all();
    }

    // ---------- Colors (normalize to [{name, hex}]) ----------
    $bootColors = [];
    $oldColors = old('colors');

    if (is_array($oldColors)) {
        foreach ($oldColors as $c) {
            if (!is_array($c)) continue;

            $name = trim($c['name'] ?? '');
            $hex  = strtoupper(trim($c['color_code'] ?? ($c['hex'] ?? '')));

            if ($name !== '') {
                if (!preg_match('/^#([0-9A-F]{6})$/i', $hex)) $hex = '#000000';
                $bootColors[] = ['name' => $name, 'hex' => $hex];
            }
        }
    } else {
        foreach ($product->colors as $c) {
            $hex = strtoupper($c->color_code ?? '#000000');
            if (!preg_match('/^#([0-9A-F]{6})$/i', $hex)) $hex = '#000000';
            $bootColors[] = ['name' => $c->name, 'hex' => $hex];
        }
    }

    // ---------- Existing images payload for Alpine ----------
    $existingImages = $product->images
        ->map(function ($img) {
            return [
                'id' => $img->id,
                'url' => asset('storage/' . $img->image_path),
                'colorHex' => strtoupper($img->color_code ?? ''),
                'isThumb' => (bool) $img->thumbnail,
            ];
        })
        ->values()
        ->all();

    // existing thumb id
    $existingThumbId = optional($product->images->firstWhere('thumbnail', true))->id;

    // ---------- STOCK MATRIX boot ----------
    $bootStock = old('stock_matrix');

    if (!is_array($bootStock)) {
        // Map DB IDs → UI indices (order matters; same order as $product->colors/$product->sizes)
        $colorIndexById = [];
        foreach ($product->colors as $idx => $c) {
            $colorIndexById[$c->id] = (string) $idx;
        }

        $sizeIndexById = [];
        foreach ($product->sizes as $idx => $s) {
            $sizeIndexById[$s->id] = (string) $idx;
        }

        $bootStock = [];
        foreach ($product->stock as $row) {
            $ciKey = is_null($row->color_id) ? 'na' : ($colorIndexById[$row->color_id] ?? null);
            $siKey = is_null($row->size_id)  ? 'na' : ($sizeIndexById[$row->size_id] ?? null);

            if ($ciKey === null || $siKey === null) continue;

            $bootStock[$ciKey][$siKey] = (int) $row->quantity_on_hand;
        }
    }

    // ---------- PRICE MATRIX boot (NEW) ----------
    // old('price_matrix') shape:
    // price_matrix[colorKey][sizeKey][price|discounted_price]
    $bootPrice = old('price_matrix');

    if (!is_array($bootPrice)) {
        $colorIndexById = [];
        foreach ($product->colors as $idx => $c) {
            $colorIndexById[$c->id] = (string) $idx;
        }

        $sizeIndexById = [];
        foreach ($product->sizes as $idx => $s) {
            $sizeIndexById[$s->id] = (string) $idx;
        }

        $bootPrice = [];
        foreach ($product->prices as $row) { // requires $product->prices relation
            $ciKey = is_null($row->color_id) ? 'na' : ($colorIndexById[$row->color_id] ?? null);
            $siKey = is_null($row->size_id)  ? 'na' : ($sizeIndexById[$row->size_id] ?? null);

            if ($ciKey === null || $siKey === null) continue;

            $bootPrice[$ciKey][$siKey] = [
                'price' => $row->price !== null ? (string) $row->price : '',
                'discounted_price' => $row->discounted_price !== null ? (string) $row->discounted_price : '',
            ];
        }
    }
@endphp


{{-- =========================================================================
| 2) ALPINE COMPONENT
|----------------------------------------------------------------------------
| One Alpine component controls:
| - sizes + colors lists
| - stock matrix
| - price matrix (NEW)
| - existing images + new images + thumbnail selection
============================================================================ --}}
<script>
window.productEditor = function(init) {
    init = init || {};

    return {
        /* -------------------------
         * A) OPTIONS: Sizes
         * ------------------------- */
        sizes: Array.isArray(init.sizes) ? init.sizes : [],
        sizeInput: '',
        addSize() {
            const v = (this.sizeInput || '').trim();
            if (v && !this.sizes.includes(v)) this.sizes.push(v);
            this.sizeInput = '';
            this.normalizeStock();
            this.normalizePrices(); // NEW
        },
        removeSize(i) {
            this.sizes.splice(i, 1);
            this.normalizeStock();
            this.normalizePrices(); // NEW
        },

        /* -------------------------
         * B) OPTIONS: Colors
         * ------------------------- */
        colors: Array.isArray(init.colors) ? init.colors : [],
        colorName: '',
        colorHex: '#000000',
        addColor() {
            const n = (this.colorName || '').trim();
            const h = (this.colorHex || '').trim();
            if (!n || !h) return;

            this.colors.push({ name: n, hex: h.toUpperCase() });
            this.colorName = '';
            this.colorHex = '#000000';

            this.normalizeStock();
            this.normalizePrices(); // NEW
        },
        removeColor(i) {
            this.colors.splice(i, 1);
            this.normalizeStock();
            this.normalizePrices(); // NEW
        },

        /* -------------------------
         * C) STOCK MATRIX
         * shape: stock[colorKey][sizeKey] = int
         * ------------------------- */
        stock: {},
        initStock(initial) {
            if (initial && typeof initial === 'object') this.stock = initial;
            this.normalizeStock();
        },
        normalizeStock() {
            const colorKeys = this.hasColors() ? [...Array(this.colors.length).keys()].map(String) : ['na'];
            const sizeKeys  = this.hasSizes()  ? [...Array(this.sizes.length).keys()].map(String)  : ['na'];

            colorKeys.forEach(ck => {
                this.stock[ck] = this.stock[ck] || {};
                sizeKeys.forEach(sk => {
                    if (this.stock[ck][sk] == null) this.stock[ck][sk] = 0;
                });
            });
        },

        /* -------------------------
         * D) PRICE MATRIX (NEW)
         * shape: prices[colorKey][sizeKey] = { price:'', discounted_price:'' }
         * ------------------------- */
        prices: {},
        initPrices(initial) {
            if (initial && typeof initial === 'object') this.prices = initial;
            this.normalizePrices();
        },
        normalizePrices() {
            const colorKeys = this.hasColors() ? [...Array(this.colors.length).keys()].map(String) : ['na'];
            const sizeKeys  = this.hasSizes()  ? [...Array(this.sizes.length).keys()].map(String)  : ['na'];

            colorKeys.forEach(ck => {
                this.prices[ck] = this.prices[ck] || {};
                sizeKeys.forEach(sk => {
                    if (!this.prices[ck][sk] || typeof this.prices[ck][sk] !== 'object') {
                        this.prices[ck][sk] = { price: '', discounted_price: '' };
                    } else {
                        this.prices[ck][sk].price = this.prices[ck][sk].price ?? '';
                        this.prices[ck][sk].discounted_price = this.prices[ck][sk].discounted_price ?? '';
                    }
                });
            });
        },

        /* -------------------------
         * E) SHARED helpers for axes
         * ------------------------- */
        hasColors() { return (this.colors?.length || 0) > 0 },
        hasSizes()  { return (this.sizes?.length  || 0) > 0 },
        matrixCount() {
            if (this.hasColors() && this.hasSizes()) return this.colors.length * this.sizes.length;
            if (this.hasColors()) return this.colors.length;
            if (this.hasSizes())  return this.sizes.length;
            return 0;
        },

        key(v) { return (v === null || v === undefined) ? 'na' : String(v) },

        /* -------------------------
         * F) STOCK getters/setters + totals
         * ------------------------- */
        getQty(ci, si) {
            const ck = this.key(ci), sk = this.key(si);
            return Number((this.stock?.[ck]?.[sk]) ?? 0);
        },
        setQty(ci, si, val) {
            const ck = this.key(ci), sk = this.key(si);
            const n = Math.max(0, parseInt(val, 10) || 0);
            if (!this.stock[ck]) this.stock[ck] = {};
            this.stock[ck][sk] = n;
        },
        rowTotal(si) {
            if (!this.hasColors()) return this.getQty(null, si);
            let t = 0; for (let ci = 0; ci < this.colors.length; ci++) t += this.getQty(ci, si);
            return t;
        },
        columnTotal(ci) {
            if (!this.hasSizes()) return this.getQty(ci, null);
            let t = 0; for (let si = 0; si < this.sizes.length; si++) t += this.getQty(ci, si);
            return t;
        },
        grandTotal() {
            if (this.hasColors() && this.hasSizes()) {
                let t = 0; for (let si = 0; si < this.sizes.length; si++) t += this.rowTotal(si);
                return t;
            }
            if (this.hasColors()) {
                let t = 0; for (let ci = 0; ci < this.colors.length; ci++) t += this.columnTotal(ci);
                return t;
            }
            if (this.hasSizes()) {
                let t = 0; for (let si = 0; si < this.sizes.length; si++) t += this.rowTotal(si);
                return t;
            }
            return 0;
        },

        setAll(val) {
            const n = Math.max(0, parseInt(val, 10) || 0);
            const cs = this.hasColors() ? [...Array(this.colors.length).keys()] : [null];
            const ss = this.hasSizes()  ? [...Array(this.sizes.length).keys()]  : [null];
            cs.forEach(ci => ss.forEach(si => this.setQty(ci, si, n)));
        },
        clearAll() { this.setAll(0) },
        setRow(si, val) {
            const n = Math.max(0, parseInt(val, 10) || 0);
            const cs = this.hasColors() ? [...Array(this.colors.length).keys()] : [null];
            cs.forEach(ci => this.setQty(ci, si, n));
        },
        setCol(ci, val) {
            const n = Math.max(0, parseInt(val, 10) || 0);
            const ss = this.hasSizes() ? [...Array(this.sizes.length).keys()] : [null];
            ss.forEach(si => this.setQty(ci, si, n));
        },
        promptNumber() {
            const v = prompt('Quantity?');
            return v === null ? null : (parseInt(v, 10) || 0);
        },

        /* -------------------------
         * G) PRICE getters/setters + bulk (NEW)
         * ------------------------- */
        getPrice(ci, si) {
            const ck = this.key(ci), sk = this.key(si);
            return this.prices?.[ck]?.[sk]?.price ?? '';
        },
        setPrice(ci, si, val) {
            const ck = this.key(ci), sk = this.key(si);
            if (!this.prices[ck]) this.prices[ck] = {};
            if (!this.prices[ck][sk]) this.prices[ck][sk] = { price:'', discounted_price:'' };
            this.prices[ck][sk].price = (val === '' ? '' : String(val));
        },
        getDiscount(ci, si) {
            const ck = this.key(ci), sk = this.key(si);
            return this.prices?.[ck]?.[sk]?.discounted_price ?? '';
        },
        setDiscount(ci, si, val) {
            const ck = this.key(ci), sk = this.key(si);
            if (!this.prices[ck]) this.prices[ck] = {};
            if (!this.prices[ck][sk]) this.prices[ck][sk] = { price:'', discounted_price:'' };
            this.prices[ck][sk].discounted_price = (val === '' ? '' : String(val));
        },
        promptMoney(label='Value?') {
            const v = prompt(`${label}\nEnter a number (e.g., 99.99)`);
            if (v === null) return null;
            const n = Number(v);
            return Number.isFinite(n) ? n.toFixed(2) : null;
        },
        setAllPrices(val) {
            const cs = this.hasColors() ? [...Array(this.colors.length).keys()] : [null];
            const ss = this.hasSizes()  ? [...Array(this.sizes.length).keys()]  : [null];
            cs.forEach(ci => ss.forEach(si => this.setPrice(ci, si, val)));
        },
        setAllDiscounts(val) {
            const cs = this.hasColors() ? [...Array(this.colors.length).keys()] : [null];
            const ss = this.hasSizes()  ? [...Array(this.sizes.length).keys()]  : [null];
            cs.forEach(ci => ss.forEach(si => this.setDiscount(ci, si, val)));
        },
        clearPrices() {
            this.setAllPrices('');
            this.setAllDiscounts('');
        },
        setRowPrice(si, val) {
            const cs = this.hasColors() ? [...Array(this.colors.length).keys()] : [null];
            cs.forEach(ci => this.setPrice(ci, si, val));
        },
        setRowDiscount(si, val) {
            const cs = this.hasColors() ? [...Array(this.colors.length).keys()] : [null];
            cs.forEach(ci => this.setDiscount(ci, si, val));
        },
        setColPrice(ci, val) {
            const ss = this.hasSizes() ? [...Array(this.sizes.length).keys()] : [null];
            ss.forEach(si => this.setPrice(ci, si, val));
        },
        setColDiscount(ci, val) {
            const ss = this.hasSizes() ? [...Array(this.sizes.length).keys()] : [null];
            ss.forEach(si => this.setDiscount(ci, si, val));
        },

        /* -------------------------
         * H) IMAGES (existing + new)
         * ------------------------- */
        imagesExisting: (Array.isArray(init.imagesExisting) ? init.imagesExisting : []).map(img => ({
            ...img, // id, url, colorHex, isThumb
            selectedColorIndex: ''
        })),

        imagesNew: [],
        dt: new DataTransfer(),

        thumbnailExistingId: init.existingThumbId || '',
        thumbnailNewUid: '',

        setThumbExisting(id) {
            this.thumbnailExistingId = id;
            this.thumbnailNewUid = '';
        },
        setThumbNew(uid) {
            this.thumbnailNewUid = uid;
            this.thumbnailExistingId = '';
        },

        applySelectedColorExisting(img) {
            const i = img.selectedColorIndex;
            if (i === '' || this.colors[i] == null) { img.colorHex = ''; return; }
            img.colorHex = this.colors[i].hex;
        },

        handleFiles(fileList) {
            for (const file of fileList) {
                const uid = `${Date.now()}_${Math.random().toString(36).slice(2)}`;
                const reader = new FileReader();
                reader.onload = e => {
                    this.imagesNew.push({
                        uid, file, preview: e.target.result,
                        selectedColorIndex: '',
                        colorHex: ''
                    });

                    this.dt.items.add(file);
                    this.$refs.fileInput.files = this.dt.files;

                    if (!this.thumbnailExistingId && !this.thumbnailNewUid) this.thumbnailNewUid = uid;
                };
                reader.readAsDataURL(file);
            }
        },

        deleteNew(index) {
            this.imagesNew.splice(index, 1);
            const newDt = new DataTransfer();
            this.imagesNew.forEach(img => newDt.items.add(img.file));
            this.dt = newDt;
            this.$refs.fileInput.files = this.dt.files;

            if (this.thumbnailNewUid && !this.imagesNew.find(i => i.uid === this.thumbnailNewUid)) {
                this.thumbnailNewUid = '';
            }
        },

        applySelectedColorNew(img) {
            const i = img.selectedColorIndex;
            if (i === '' || this.colors[i] == null) { img.colorHex = ''; return; }
            img.colorHex = this.colors[i].hex;
        },

        async deleteExisting(img, idx) {
            if (!confirm('Delete this image?')) return;
            try {
                const res = await fetch('{{ route('admin.product.image.delete') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ id: img.id })
                });

                const json = await res.json();
                if (json.status === 'success') {
                    this.imagesExisting.splice(idx, 1);
                    if (this.thumbnailExistingId == img.id) this.thumbnailExistingId = '';
                } else {
                    alert('Failed to delete image');
                }
            } catch (e) {
                console.error(e);
                alert('Error deleting image');
            }
        },
    }
}
</script>


<div class="mx-2 bg-white dark:bg-gray-800 p-6 shadow-md rounded-tl-md rounded-tr-md">
    <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6 flex items-center">
        <span class="material-icons text-4xl font-extrabold mr-2">edit</span>
        Edit Product
    </h2>

    <form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Product Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
            <input type="text" name="name"
                class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                value="{{ old('name', $product->name) }}">
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- SLUG --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SLUG</label>
            <input type="text" name="slug"
                class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                value="{{ old('slug', $product->slug) }}">
            @error('slug') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- SKU --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
            <input type="text" name="sku"
                class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                value="{{ old('sku', $product->sku) }}">
            @error('sku') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
            <textarea class="summernote-editor" name="description">{!! old('description', $product->description) !!}</textarea>
            @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Category --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
            <select name="category_id"
                class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                <option value="">Select Category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Collection (optional) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Collection (Optional)</label>
            <select name="collection_id"
                class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                <option value="">Select Collection</option>
                @foreach ($collections as $collection)
                    <option value="{{ $collection->id }}" @selected(old('collection_id', $product->collection_id) == $collection->id)>
                        {{ $collection->name }}
                    </option>
                @endforeach
            </select>
            @error('collection_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Price & Discount (Global fallback) --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                <input type="number" name="price" step="0.01"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('price', $product->price) }}">
                @error('price') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Price</label>
                <input type="number" name="discount_price" step="0.01"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('discount_price', $product->discount_price) }}">
                @error('discount_price') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Stock (Global fallback) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity in Stock</label>
            <input type="number" name="stock_quantity"
                class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                value="{{ old('stock_quantity', $product->stock_quantity) }}">
            @error('stock_quantity') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Status --}}
        <div class="flex items-center">
            <label class="mr-4 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="status" value="1" class="sr-only peer"
                    {{ old('status', $product->status) ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer dark:bg-gray-600 peer-checked:bg-green-500"></div>
            </label>
        </div>


        {{-- ==========================================================
        | 3) ALPINE UI (sizes, colors, stock matrix, price matrix, images)
        =========================================================== --}}
        <div
            x-data="productEditor(@js([
                'sizes' => $bootSizes,
                'colors' => $bootColors,
                'imagesExisting' => $existingImages,
                'existingThumbId' => $existingThumbId,
            ]))"
            x-init="$nextTick(() => { initStock(@js($bootStock)); initPrices(@js($bootPrice)); })"
            x-cloak
            class="space-y-8"
        >

            {{-- SIZES --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sizes</label>
                <div class="mt-2 flex gap-2">
                    <input type="text" x-model="sizeInput" placeholder="e.g., XS, S, M"
                        @keydown.enter.prevent="addSize()"
                        class="w-full p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                    <button type="button" @click="addSize()" class="px-4 py-2 bg-black text-white rounded-md">Add</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <template x-for="(s, i) in sizes" :key="s + i">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700">
                            <span x-text="s" class="text-sm dark:text-white"></span>
                            <button type="button" @click="removeSize(i)" class="text-gray-500 hover:text-red-600">&times;</button>
                            <input type="hidden" name="sizes[]" :value="s">
                        </div>
                    </template>
                </div>
                @error('sizes') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- COLORS --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Colors</label>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="text" x-model="colorName" placeholder="Color name"
                        class="p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                    <input type="color" x-model="colorHex"
                        class="p-2 h-10 border rounded-md bg-white dark:bg-gray-700">
                    <button type="button" @click="addColor()" class="px-4 py-2 bg-black text-white rounded-md">Add</button>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <template x-for="(c, i) in colors" :key="c.name + c.hex + i">
                        <div class="flex items-center justify-between gap-3 p-2 border rounded-lg bg-gray-50 dark:bg-gray-700">
                            <div class="flex items-center gap-3">
                                <span class="inline-block w-6 h-6 rounded-full border" :style="`background:${c.hex}`"></span>
                                <div class="text-sm dark:text-white">
                                    <div x-text="c.name"></div>
                                    <div class="text-gray-500" x-text="c.hex"></div>
                                </div>
                            </div>

                            <input type="hidden" :name="'colors[' + i + '][name]'" :value="c.name">
                            <input type="hidden" :name="'colors[' + i + '][color_code]'" :value="c.hex">

                            <button type="button" @click="removeColor(i)" class="text-gray-500 hover:text-red-600">&times;</button>
                        </div>
                    </template>
                </div>

                @error('colors') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>


            {{-- STOCK MATRIX --}}
            <div class="mt-10">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Stock by Options</h3>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"
                            x-text="`Colors: ${colors.length || 0} · Sizes: ${sizes.length || 0} → ${matrixCount()}`"></span>

                        <button type="button" class="px-3 py-1.5 rounded-md bg-black text-white hover:bg-black/90"
                            @click="const v = promptNumber(); if(v !== null) setAll(v)">Set all</button>

                        <button type="button"
                            class="px-3 py-1.5 rounded-md bg-gray-200 dark:bg-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600"
                            @click="clearAll()">Clear all</button>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-4">
                    Set per-variant quantity. If you don’t use variants, you can rely on the global stock field above.
                </p>

                <template x-if="!hasColors() && !hasSizes()">
                    <div class="p-4 rounded-md bg-amber-50 text-amber-800">
                        Add Colors and/or Sizes above to enable per-option stock.
                    </div>
                </template>

                <template x-if="hasColors() && hasSizes()">
                    <div class="overflow-auto border rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
                                <tr>
                                    <th class="p-2 text-left text-gray-600 dark:text-gray-300 w-48">Size \ Color</th>
                                    <template x-for="(c,ci) in colors" :key="'shead-' + c.name + c.hex + ci">
                                        <th class="p-2 text-left align-bottom min-w-[160px]">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block w-4 h-4 rounded-full border" :style="`background:${c.hex}`"></span>
                                                <span class="font-medium text-gray-700 dark:text-gray-100" x-text="c.name"></span>
                                            </div>
                                            <div class="mt-2 flex items-center gap-2 text-xs">
                                                <button type="button" class="px-2 py-0.5 rounded border"
                                                    @click="const v=promptNumber(); if(v !== null) setCol(ci, v)">Set col</button>
                                                <span class="text-gray-500">Total: <span x-text="columnTotal(ci)"></span></span>
                                            </div>
                                        </th>
                                    </template>
                                </tr>
                            </thead>

                            <tbody>
                                <template x-for="(s,si) in sizes" :key="'srow-' + s + si">
                                    <tr class="border-t">
                                        <th class="p-2 align-top">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium text-gray-700 dark:text-gray-100" x-text="s"></span>
                                                <button type="button" class="px-2 py-0.5 rounded border text-xs"
                                                    @click="const v=promptNumber(); if(v !== null) setRow(si, v)">Set row</button>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">Total: <span x-text="rowTotal(si)"></span></div>
                                        </th>

                                        <template x-for="(c,ci) in colors" :key="'scell-' + ci + '-' + si">
                                            <td class="p-2">
                                                <div class="relative">
                                                    <input type="number" inputmode="numeric" min="0" step="1"
                                                        class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-black focus:border-black"
                                                        :aria-label="`Quantity for ${c.name} / ${s}`"
                                                        :value="getQty(ci, si)"
                                                        @input="setQty(ci, si, $event.target.value)">

                                                    <button type="button"
                                                        class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                                        @click="setQty(ci, si, 0)" title="Clear">×</button>

                                                    <input type="hidden" :name="`stock_matrix[${key(ci)}][${key(si)}]`"
                                                        :value="getQty(ci, si)">
                                                </div>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>

                            <tfoot class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="p-2 text-right">Grand total:</th>
                                    <template x-for="(c,ci) in colors" :key="'sfoot-' + ci">
                                        <td class="p-2 font-semibold" x-text="ci === 0 ? grandTotal() : ''"></td>
                                    </template>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </template>

                <template x-if="hasColors() && !hasSizes()">
                    <div class="grid gap-2">
                        <template x-for="(c,ci) in colors" :key="'s1c-' + c.name + c.hex + ci">
                            <div class="flex items-center justify-between border rounded-md p-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-5 h-5 rounded-full border" :style="`background:${c.hex}`"></span>
                                    <span class="font-medium" x-text="c.name"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" step="1"
                                        class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        :value="getQty(ci, null)" @input="setQty(ci, null, $event.target.value)">

                                    <input type="hidden" :name="`stock_matrix[${key(ci)}][na]`" :value="getQty(ci, null)">
                                </div>
                            </div>
                        </template>
                        <div class="text-right text-sm text-gray-600 dark:text-gray-300">Total: <span x-text="grandTotal()"></span></div>
                    </div>
                </template>

                <template x-if="!hasColors() && hasSizes()">
                    <div class="grid gap-2">
                        <template x-for="(s,si) in sizes" :key="'s1s-' + s + si">
                            <div class="flex items-center justify-between border rounded-md p-2">
                                <span class="font-medium" x-text="s"></span>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" step="1"
                                        class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        :value="getQty(null, si)" @input="setQty(null, si, $event.target.value)">

                                    <input type="hidden" :name="`stock_matrix[na][${key(si)}]`" :value="getQty(null, si)">
                                </div>
                            </div>
                        </template>
                        <div class="text-right text-sm text-gray-600 dark:text-gray-300">Total: <span x-text="grandTotal()"></span></div>
                    </div>
                </template>
            </div>


            {{-- PRICE MATRIX (NEW) --}}
            <div class="mt-10">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Pricing by Options</h3>

                    <div class="flex items-center gap-2 text-sm">
                        <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"
                            x-text="`Variants: ${matrixCount()}`"></span>

                        <button type="button" class="px-3 py-1.5 rounded-md bg-black text-white hover:bg-black/90"
                            @click="const v = promptMoney('Set all prices'); if(v !== null) setAllPrices(v)">Set all Price</button>

                        <button type="button" class="px-3 py-1.5 rounded-md bg-black text-white hover:bg-black/90"
                            @click="const v = promptMoney('Set all discounted'); if(v !== null) setAllDiscounts(v)">Set all Discount</button>

                        <button type="button"
                            class="px-3 py-1.5 rounded-md bg-gray-200 dark:bg-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600"
                            @click="clearPrices()">Clear</button>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-4">
                    Fill variant price/discount here. Leave blank to fall back to the global Price/Discount inputs above.
                </p>

                <template x-if="!hasColors() && !hasSizes()">
                    <div class="p-4 rounded-md bg-amber-50 text-amber-800">
                        Add Colors and/or Sizes above to enable per-option pricing.
                    </div>
                </template>

                {{-- 2D pricing --}}
                <template x-if="hasColors() && hasSizes()">
                    <div class="overflow-auto border rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
                                <tr>
                                    <th class="p-2 text-left text-gray-600 dark:text-gray-300 w-48">Size \ Color</th>
                                    <template x-for="(c,ci) in colors" :key="'phead-' + c.name + c.hex + ci">
                                        <th class="p-2 text-left align-bottom min-w-[240px]">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block w-4 h-4 rounded-full border" :style="`background:${c.hex}`"></span>
                                                <span class="font-medium text-gray-700 dark:text-gray-100" x-text="c.name"></span>
                                            </div>

                                            <div class="mt-2 flex items-center gap-2 text-xs flex-wrap">
                                                <button type="button" class="px-2 py-0.5 rounded border"
                                                    @click="const v=promptMoney('Set column price'); if(v !== null) setColPrice(ci, v)">Set col Price</button>
                                                <button type="button" class="px-2 py-0.5 rounded border"
                                                    @click="const v=promptMoney('Set column discount'); if(v !== null) setColDiscount(ci, v)">Set col Discount</button>
                                            </div>
                                        </th>
                                    </template>
                                </tr>
                            </thead>

                            <tbody>
                                <template x-for="(s,si) in sizes" :key="'prow-' + s + si">
                                    <tr class="border-t">
                                        <th class="p-2 align-top">
                                            <span class="font-medium text-gray-700 dark:text-gray-100" x-text="s"></span>

                                            <div class="mt-2 flex items-center gap-2 text-xs flex-wrap">
                                                <button type="button" class="px-2 py-0.5 rounded border"
                                                    @click="const v=promptMoney('Set row price'); if(v !== null) setRowPrice(si, v)">Set row Price</button>
                                                <button type="button" class="px-2 py-0.5 rounded border"
                                                    @click="const v=promptMoney('Set row discount'); if(v !== null) setRowDiscount(si, v)">Set row Discount</button>
                                            </div>
                                        </th>

                                        <template x-for="(c,ci) in colors" :key="'pcell-' + ci + '-' + si">
                                            <td class="p-2">
                                                <div class="grid grid-cols-2 gap-2 min-w-[220px]">
                                                    <div class="relative">
                                                        <input type="number" step="0.01" min="0"
                                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-black focus:border-black"
                                                            placeholder="Price"
                                                            :value="getPrice(ci, si)"
                                                            @input="setPrice(ci, si, $event.target.value)">

                                                        <button type="button"
                                                            class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                                            @click="setPrice(ci, si, '')" title="Clear">×</button>

                                                        <input type="hidden"
                                                            :name="`price_matrix[${key(ci)}][${key(si)}][price]`"
                                                            :value="getPrice(ci, si)">
                                                    </div>

                                                    <div class="relative">
                                                        <input type="number" step="0.01" min="0"
                                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-black focus:border-black"
                                                            placeholder="Discount"
                                                            :value="getDiscount(ci, si)"
                                                            @input="setDiscount(ci, si, $event.target.value)">

                                                        <button type="button"
                                                            class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                                            @click="setDiscount(ci, si, '')" title="Clear">×</button>

                                                        <input type="hidden"
                                                            :name="`price_matrix[${key(ci)}][${key(si)}][discounted_price]`"
                                                            :value="getDiscount(ci, si)">
                                                    </div>
                                                </div>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                {{-- 1D pricing (only colors) --}}
                <template x-if="hasColors() && !hasSizes()">
                    <div class="grid gap-2">
                        <template x-for="(c,ci) in colors" :key="'p1c-' + c.name + c.hex + ci">
                            <div class="flex items-center justify-between border rounded-md p-2 gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-5 h-5 rounded-full border" :style="`background:${c.hex}`"></span>
                                    <span class="font-medium" x-text="c.name"></span>
                                </div>

                                <div class="grid grid-cols-2 gap-2 w-[320px] max-w-full">
                                    <div class="relative">
                                        <input type="number" step="0.01" min="0"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="Price"
                                            :value="getPrice(ci, null)"
                                            @input="setPrice(ci, null, $event.target.value)">
                                        <button type="button" class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                            @click="setPrice(ci, null, '')">×</button>
                                        <input type="hidden" :name="`price_matrix[${key(ci)}][na][price]`" :value="getPrice(ci, null)">
                                    </div>

                                    <div class="relative">
                                        <input type="number" step="0.01" min="0"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="Discount"
                                            :value="getDiscount(ci, null)"
                                            @input="setDiscount(ci, null, $event.target.value)">
                                        <button type="button" class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                            @click="setDiscount(ci, null, '')">×</button>
                                        <input type="hidden" :name="`price_matrix[${key(ci)}][na][discounted_price]`" :value="getDiscount(ci, null)">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- 1D pricing (only sizes) --}}
                <template x-if="!hasColors() && hasSizes()">
                    <div class="grid gap-2">
                        <template x-for="(s,si) in sizes" :key="'p1s-' + s + si">
                            <div class="flex items-center justify-between border rounded-md p-2 gap-3">
                                <span class="font-medium" x-text="s"></span>

                                <div class="grid grid-cols-2 gap-2 w-[320px] max-w-full">
                                    <div class="relative">
                                        <input type="number" step="0.01" min="0"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="Price"
                                            :value="getPrice(null, si)"
                                            @input="setPrice(null, si, $event.target.value)">
                                        <button type="button" class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                            @click="setPrice(null, si, '')">×</button>
                                        <input type="hidden" :name="`price_matrix[na][${key(si)}][price]`" :value="getPrice(null, si)">
                                    </div>

                                    <div class="relative">
                                        <input type="number" step="0.01" min="0"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="Discount"
                                            :value="getDiscount(null, si)"
                                            @input="setDiscount(null, si, $event.target.value)">
                                        <button type="button" class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                                            @click="setDiscount(null, si, '')">×</button>
                                        <input type="hidden" :name="`price_matrix[na][${key(si)}][discounted_price]`" :value="getDiscount(null, si)">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>


            {{-- EXISTING IMAGES --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Existing Images</label>

                <input type="hidden" name="thumbnail_existing_id" x-model="thumbnailExistingId">
                <input type="hidden" name="thumbnail_new_uid" x-model="thumbnailNewUid">

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-2">
                    <template x-for="(img, idx) in imagesExisting" :key="'ex_' + img.id">
                        <div class="relative group border rounded-lg overflow-hidden bg-white dark:bg-gray-800">

                            <div class="absolute top-3 left-3 z-10 flex items-center gap-2 bg-white/80 dark:bg-gray-900/70 px-2 py-1 rounded-full">
                                <input type="radio" name="thumb_choice" :value="'existing:' + img.id"
                                    @change="setThumbExisting(img.id)" :checked="img.isThumb"
                                    class="form-radio text-blue-500 focus:ring-blue-400">
                                <span class="text-xs text-gray-700 dark:text-gray-200">Thumb</span>
                            </div>

                            <img :src="img.url" class="w-full h-40 object-cover" alt="Existing image">

                            <div class="p-3 space-y-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Assign color</label>
                                <select x-model="img.selectedColorIndex" @change="applySelectedColorExisting(img)"
                                    class="w-full px-2 py-1.5 rounded-md border bg-white dark:bg-gray-700 dark:text-white">
                                    <option value="">— Select color —</option>
                                    <template x-for="(c,i) in colors" :key="'exsel_' + img.id + '_' + i">
                                        <option :value="i" x-text="`${c.name} (${c.hex})`" :selected="img.colorHex === c.hex"></option>
                                    </template>
                                </select>

                                <div class="flex items-center gap-3" x-show="img.colorHex">
                                    <span class="inline-block w-6 h-6 rounded-full border" :style="`background:${img.colorHex}`"></span>
                                    <div class="text-xs text-gray-600 dark:text-gray-300" x-text="img.colorHex"></div>
                                </div>

                                <input type="hidden" :name="'image_existing[' + img.id + '][color_code]'" :value="img.colorHex || ''">
                            </div>

                            <button type="button" @click="deleteExisting(img, idx)"
                                class="absolute top-3 right-3 bg-white/80 dark:bg-gray-900/70 hover:bg-white text-red-600 rounded-full p-1.5 shadow">
                                <span class="material-icons text-[18px]">delete</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- NEW IMAGES --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add New Images</label>

                <input name="images[]" type="file" multiple x-ref="fileInput" @change="handleFiles($event.target.files)" class="hidden">

                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 rounded-lg cursor-pointer
                    bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition text-center
                    text-gray-600 dark:text-gray-300"
                    @click="$refs.fileInput.click()" @dragover.prevent @drop.prevent="handleFiles($event.dataTransfer.files)">
                    Click or drag files here to upload
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    <template x-for="(img, idx) in imagesNew" :key="'new_' + img.uid">
                        <div class="relative group border rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                            <input type="hidden" :name="'new_image_uids[' + idx + ']'" :value="img.uid">

                            <div class="absolute top-3 left-3 z-10 flex items-center gap-2 bg-white/80 dark:bg-gray-900/70 px-2 py-1 rounded-full">
                                <input type="radio" name="thumb_choice" :value="'new:' + img.uid"
                                    @change="setThumbNew(img.uid)"
                                    class="form-radio text-blue-500 focus:ring-blue-400">
                                <span class="text-xs text-gray-700 dark:text-gray-200">Thumb</span>
                            </div>

                            <img :src="img.preview" class="w-full h-40 object-cover" alt="New image preview">

                            <div class="p-3 space-y-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Assign color</label>
                                <select x-model="img.selectedColorIndex" @change="applySelectedColorNew(img)"
                                    class="w-full px-2 py-1.5 rounded-md border bg-white dark:bg-gray-700 dark:text-white">
                                    <option value="">— Select color —</option>
                                    <template x-for="(c,i) in colors" :key="'newsel_' + img.uid + '_' + i">
                                        <option :value="i" x-text="`${c.name} (${c.hex})`"></option>
                                    </template>
                                </select>

                                <div class="flex items-center gap-3" x-show="img.colorHex">
                                    <span class="inline-block w-6 h-6 rounded-full border" :style="`background:${img.colorHex}`"></span>
                                    <div class="text-xs text-gray-600 dark:text-gray-300" x-text="img.colorHex"></div>
                                </div>

                                <input type="hidden" :name="'image_color_hexes[' + idx + ']'" :value="img.colorHex || ''">
                            </div>

                            <button type="button" @click="deleteNew(idx)"
                                class="absolute top-3 right-3 bg-white/80 dark:bg-gray-900/70 hover:bg-white text-red-600 rounded-full p-1.5 shadow">
                                <span class="material-icons text-[18px]">delete</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        {{-- Submit --}}
        <div>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Update Product
            </button>
        </div>
    </form>
</div>

@endsection
