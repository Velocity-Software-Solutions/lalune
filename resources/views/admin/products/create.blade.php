@extends('layouts.admin')

@section('title', 'Add Product')

@push('head')
  @vite(['resources/js/summernote.js'])
@endpush

@section('content')
@php
  /*
  |--------------------------------------------------------------------------
  | 1) BOOTSTRAP DATA (old() only, because it's "create")
  |--------------------------------------------------------------------------
  | If validation fails, old() repopulates the UI.
  */

  // Sizes
  $bootSizes = old('sizes', []);
  if (!is_array($bootSizes)) $bootSizes = [];

  // Colors normalize to [{name, hex}]
  $bootColors = [];
  foreach ((array) old('colors', []) as $c) {
      if (!is_array($c)) continue;
      $name = trim($c['name'] ?? '');
      $hex  = strtoupper(trim($c['color_code'] ?? ($c['hex'] ?? '')));
      if ($name !== '') {
          if (!preg_match('/^#([0-9A-F]{6})$/i', $hex)) $hex = '#000000';
          $bootColors[] = ['name' => $name, 'hex' => $hex];
      }
  }

  // Stock matrix old() bootstrap
  $bootStock = old('stock_matrix', []);
  if (!is_array($bootStock)) $bootStock = [];

  // Price matrix old() bootstrap (NEW)
  // price_matrix[colorKey][sizeKey][price|discounted_price]
  $bootPrice = old('price_matrix', []);
  if (!is_array($bootPrice)) $bootPrice = [];
@endphp

<script>
function productComposer(init) {
  init = init || {};

  return {
    /* -------------------------
     * A) Sizes
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
     * B) Colors
     * ------------------------- */
    colors: Array.isArray(init.colors) ? init.colors : [],
    colorName: '',
    colorHex: '#000000',
    addColor() {
      const name = (this.colorName || '').trim();
      const hex  = (this.colorHex  || '').trim();
      if (!name || !hex) return;

      this.colors.push({ name, hex: hex.toUpperCase() });
      this.colorName = '';
      this.colorHex  = '#000000';

      this.normalizeStock();
      this.normalizePrices(); // NEW
    },
    removeColor(i) {
      this.colors.splice(i, 1);
      this.normalizeStock();
      this.normalizePrices(); // NEW
    },

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
     * C) Stock Matrix
     * stock[colorKey][sizeKey] = int
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
      if (this.hasColors() && this.hasSizes()) { let t=0; for (let si=0; si<this.sizes.length; si++) t += this.rowTotal(si); return t; }
      if (this.hasColors()) { let t=0; for (let ci=0; ci<this.colors.length; ci++) t += this.columnTotal(ci); return t; }
      if (this.hasSizes())  { let t=0; for (let si=0; si<this.sizes.length; si++) t += this.rowTotal(si); return t; }
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
     * D) Price Matrix (NEW)
     * prices[colorKey][sizeKey] = {price:'', discounted_price:''}
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
    clearPrices() { this.setAllPrices(''); this.setAllDiscounts(''); },

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
     * E) Images + Thumbnail
     * ------------------------- */
    images: [], // { uid, file, preview, selectedColorIndex:'', colorHex:'' }
    thumbnailId: null,
    dt: new DataTransfer(),

    handleFiles(fileList) {
      for (const file of fileList) {
        const uid = `${Date.now()}_${Math.random().toString(36).slice(2)}`;
        const reader = new FileReader();
        reader.onload = e => {
          this.images.push({
            uid, file, preview: e.target.result,
            selectedColorIndex: '',
            colorHex: ''
          });
          this.dt.items.add(file);
          this.$refs.fileInput.files = this.dt.files;
          if (!this.thumbnailId) this.thumbnailId = uid;
        };
        reader.readAsDataURL(file);
      }
    },
    deleteImage(index) {
      const removed = this.images.splice(index, 1)[0];
      const newDt = new DataTransfer();
      this.images.forEach(img => newDt.items.add(img.file));
      this.dt = newDt;
      this.$refs.fileInput.files = this.dt.files;
      if (this.thumbnailId === removed?.uid) this.thumbnailId = this.images[0]?.uid || null;
    },
    setThumbnail(img) { this.thumbnailId = img.uid; },

    applySelectedColor(img) {
      const i = img.selectedColorIndex;
      if (i === '' || this.colors[i] == null) { img.colorHex = ''; return; }
      img.colorHex = this.colors[i].hex;
    },
  };
}
</script>

<div class="mx-2 bg-white dark:bg-gray-800 p-6 shadow-md rounded-tl-md rounded-tr-md overflow-scroll custom-scrollbar scrollbar-hide">
  <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6 flex items-center">
    <span class="material-icons text-4xl font-extrabold">add</span>
    Add New Product
  </h2>

  <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf

    {{-- Product Name --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
      <input type="text" name="name"
        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
        value="{{ old('name') }}">
      @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- SLUG --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SLUG</label>
      <input type="text" name="slug"
        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
        value="{{ old('slug') }}">
      @error('slug') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- SKU --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
      <input type="text" name="sku"
        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
        value="{{ old('sku') }}">
      @error('sku') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Description --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
      <textarea class="summernote-editor" name="description">{!! old('description', '') !!}</textarea>
      @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Category --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
      <select name="category_id" class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
        <option value="">Select Category</option>
        @foreach ($categories as $category)
          <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
        @endforeach
      </select>
      @error('category_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Collection --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Collection (Optional)</label>
      <select name="collection_id" class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
        <option value="">Select Collection</option>
        @foreach ($collections as $collection)
          <option value="{{ $collection->id }}" @selected(old('collection_id') == $collection->id)>{{ $collection->name }}</option>
        @endforeach
      </select>
      @error('collection_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Global Price & Discount (fallback) --}}
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
        <input type="number" name="price" step="0.01"
          class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
          value="{{ old('price') }}">
        @error('price') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Price</label>
        <input type="number" name="discount_price" step="0.01"
          class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
          value="{{ old('discount_price') }}">
        @error('discount_price') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
      </div>
    </div>

    {{-- Global Stock (fallback) --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity in Stock</label>
      <input type="number" name="stock_quantity"
        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
        value="{{ old('stock_quantity') }}">
      @error('stock_quantity') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Status --}}
    <div class="flex items-center">
      <label class="mr-4 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
      <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" name="status" value="1" class="sr-only peer" {{ old('status') ? 'checked' : '' }}>
        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer dark:bg-gray-600 peer-checked:bg-green-500"></div>
      </label>
    </div>


    {{-- ==========================================================
       Alpine Area
    =========================================================== --}}
    <div
      x-data="productComposer(@js([
        'sizes' => $bootSizes,
        'colors' => $bootColors,
      ]))"
      x-init="$nextTick(() => { initStock(@js($bootStock)); initPrices(@js($bootPrice)); })"
      x-cloak
      class="space-y-8"
    >

      {{-- Sizes --}}
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

      {{-- Colors --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Colors</label>

        <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
          <input type="text" x-model="colorName" placeholder="Color name"
            class="p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
          <input type="color" x-model="colorHex" class="p-2 h-10 border rounded-md bg-white dark:bg-gray-700">
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


      {{-- Stock by Options --}}
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

        <template x-if="!hasColors() && !hasSizes()">
          <div class="p-4 rounded-md bg-amber-50 text-amber-800">
            Add Colors and/or Sizes above to enable per-option stock.
          </div>
        </template>

        {{-- 2D --}}
        <template x-if="hasColors() && hasSizes()">
          <div class="overflow-auto border rounded-lg">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
                <tr>
                  <th class="p-2 text-left text-gray-600 dark:text-gray-300 w-48">Size \ Color</th>
                  <template x-for="(c,ci) in colors" :key="'head-' + c.name + c.hex + ci">
                    <th class="p-2 text-left min-w-[160px]">
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
                <template x-for="(s,si) in sizes" :key="'row-' + s + si">
                  <tr class="border-t">
                    <th class="p-2 align-top">
                      <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-700 dark:text-gray-100" x-text="s"></span>
                        <button type="button" class="px-2 py-0.5 rounded border text-xs"
                          @click="const v=promptNumber(); if(v !== null) setRow(si, v)">Set row</button>
                      </div>
                      <div class="mt-1 text-xs text-gray-500">Total: <span x-text="rowTotal(si)"></span></div>
                    </th>

                    <template x-for="(c,ci) in colors" :key="'cell-' + ci + '-' + si">
                      <td class="p-2">
                        <div class="relative">
                          <input type="number" min="0" step="1"
                            class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-black focus:border-black"
                            :value="getQty(ci, si)" @input="setQty(ci, si, $event.target.value)">

                          <button type="button"
                            class="absolute right-1 top-1 text-xs text-gray-400 hover:text-red-600"
                            @click="setQty(ci, si, 0)" title="Clear">×</button>

                          <input type="hidden" :name="`stock_matrix[${key(ci)}][${key(si)}]`" :value="getQty(ci, si)">
                        </div>
                      </td>
                    </template>
                  </tr>
                </template>
              </tbody>

              <tfoot class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="p-2 text-right">Grand total:</th>
                  <template x-for="(c,ci) in colors" :key="'foot-' + ci">
                    <td class="p-2 font-semibold" x-text="ci === 0 ? grandTotal() : ''"></td>
                  </template>
                </tr>
              </tfoot>
            </table>
          </div>
        </template>

        {{-- 1D colors --}}
        <template x-if="hasColors() && !hasSizes()">
          <div class="grid gap-2">
            <template x-for="(c,ci) in colors" :key="'onlyc-' + ci">
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

        {{-- 1D sizes --}}
        <template x-if="!hasColors() && hasSizes()">
          <div class="grid gap-2">
            <template x-for="(s,si) in sizes" :key="'onlys-' + si">
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


      {{-- Pricing by Options (NEW) --}}
      <div class="mt-10">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Pricing by Options</h3>
          <div class="flex items-center gap-2 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-md bg-black text-white hover:bg-black/90"
              @click="const v=promptMoney('Set all prices'); if(v !== null) setAllPrices(v)">Set all Price</button>

            <button type="button" class="px-3 py-1.5 rounded-md bg-black text-white hover:bg-black/90"
              @click="const v=promptMoney('Set all discounts'); if(v !== null) setAllDiscounts(v)">Set all Discount</button>

            <button type="button"
              class="px-3 py-1.5 rounded-md bg-gray-200 dark:bg-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600"
              @click="clearPrices()">Clear</button>
          </div>
        </div>

        <p class="text-xs text-gray-500 mb-4">Leave empty to fallback to the global price/discount above.</p>

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
                  <template x-for="(c,ci) in colors" :key="'phead-' + ci">
                    <th class="p-2 text-left min-w-[240px]">
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
                <template x-for="(s,si) in sizes" :key="'prow-' + si">
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
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                              placeholder="Price"
                              :value="getPrice(ci, si)"
                              @input="setPrice(ci, si, $event.target.value)">
                            <input type="hidden" :name="`price_matrix[${key(ci)}][${key(si)}][price]`" :value="getPrice(ci, si)">
                          </div>

                          <div class="relative">
                            <input type="number" step="0.01" min="0"
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                              placeholder="Discount"
                              :value="getDiscount(ci, si)"
                              @input="setDiscount(ci, si, $event.target.value)">
                            <input type="hidden" :name="`price_matrix[${key(ci)}][${key(si)}][discounted_price]`" :value="getDiscount(ci, si)">
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
            <template x-for="(c,ci) in colors" :key="'p1c-' + ci">
              <div class="flex items-center justify-between border rounded-md p-2 gap-3">
                <div class="flex items-center gap-2">
                  <span class="inline-block w-5 h-5 rounded-full border" :style="`background:${c.hex}`"></span>
                  <span class="font-medium" x-text="c.name"></span>
                </div>

                <div class="grid grid-cols-2 gap-2 w-[320px] max-w-full">
                  <input type="number" step="0.01" min="0"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Price"
                    :value="getPrice(ci, null)"
                    @input="setPrice(ci, null, $event.target.value)">
                  <input type="number" step="0.01" min="0"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Discount"
                    :value="getDiscount(ci, null)"
                    @input="setDiscount(ci, null, $event.target.value)">

                  <input type="hidden" :name="`price_matrix[${key(ci)}][na][price]`" :value="getPrice(ci, null)">
                  <input type="hidden" :name="`price_matrix[${key(ci)}][na][discounted_price]`" :value="getDiscount(ci, null)">
                </div>
              </div>
            </template>
          </div>
        </template>

        {{-- 1D pricing (only sizes) --}}
        <template x-if="!hasColors() && hasSizes()">
          <div class="grid gap-2">
            <template x-for="(s,si) in sizes" :key="'p1s-' + si">
              <div class="flex items-center justify-between border rounded-md p-2 gap-3">
                <span class="font-medium" x-text="s"></span>

                <div class="grid grid-cols-2 gap-2 w-[320px] max-w-full">
                  <input type="number" step="0.01" min="0"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Price"
                    :value="getPrice(null, si)"
                    @input="setPrice(null, si, $event.target.value)">
                  <input type="number" step="0.01" min="0"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Discount"
                    :value="getDiscount(null, si)"
                    @input="setDiscount(null, si, $event.target.value)">

                  <input type="hidden" :name="`price_matrix[na][${key(si)}][price]`" :value="getPrice(null, si)">
                  <input type="hidden" :name="`price_matrix[na][${key(si)}][discounted_price]`" :value="getDiscount(null, si)">
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>


      {{-- Images --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Images</label>

        <input name="images[]" type="file" multiple x-ref="fileInput" @change="handleFiles($event.target.files)" class="hidden">
        <input type="hidden" name="thumbnail_id" :value="thumbnailId">

        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 rounded-lg cursor-pointer
          bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition text-center
          text-gray-600 dark:text-gray-300"
          @click="$refs.fileInput.click()" @dragover.prevent @drop.prevent="handleFiles($event.dataTransfer.files)">
          Click or drag files here to upload
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
          <template x-for="(img, idx) in images" :key="img.uid">
            <div class="relative group border rounded-lg overflow-hidden bg-white dark:bg-gray-800">
              <div class="absolute top-3 left-3 z-10 flex items-center gap-2 bg-white/80 dark:bg-gray-900/70 px-2 py-1 rounded-full">
                <input type="radio" :value="img.uid" x-model="thumbnailId" @change="setThumbnail(img)"
                  class="form-radio text-blue-500 focus:ring-blue-400">
                <span class="text-xs text-gray-700 dark:text-gray-200">Thumb</span>
              </div>

              <img :src="img.preview" class="w-full h-40 object-cover" alt="Image preview">

              <div class="p-3 space-y-2">
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Assign color</label>
                <select x-model="img.selectedColorIndex" @change="applySelectedColor(img)"
                  class="w-full px-2 py-1.5 rounded-md border bg-white dark:bg-gray-700 dark:text-white">
                  <option value="">— Select color —</option>
                  <template x-for="(c,i) in colors" :key="'imgc-' + i">
                    <option :value="i" x-text="`${c.name} (${c.hex})`"></option>
                  </template>
                </select>

                <div class="flex items-center gap-3" x-show="img.colorHex">
                  <span class="inline-block w-6 h-6 rounded-full border" :style="`background:${img.colorHex}`"></span>
                  <div class="text-xs text-gray-600 dark:text-gray-300" x-text="img.colorHex"></div>
                </div>

                <input type="hidden" name="image_color_hexes[]" :value="img.colorHex || ''">
              </div>

              <button type="button" @click.stop="deleteImage(idx)"
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
        Save Product
      </button>
    </div>
  </form>
</div>
@endsection
