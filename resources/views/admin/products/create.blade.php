@extends('layouts.admin')

@section('title', 'Add Product')
@push('head')
    @vite(['resources/js/summernote.js'])
@endpush
@section('content')
    @php
        // Sizes
        $bootSizes = old('sizes', []);
        if (!is_array($bootSizes)) {
            $bootSizes = [];
        }

        // Colors: normalize to ['name' => ..., 'hex' => ...]
        $bootColors = [];
        foreach ((array) old('colors', []) as $c) {
            if (!is_array($c)) {
                continue;
            }

            $name = trim($c['name'] ?? '');
            $hex = strtoupper(trim($c['color_code'] ?? ($c['hex'] ?? '')));

            if ($name !== '') {
                // Fallback hex if missing/invalid
                if (!preg_match('/^#([0-9A-F]{6})$/i', $hex)) {
                    $hex = '#000000';
                }
                $bootColors[] = ['name' => $name, 'hex' => $hex];
            }
        }
    @endphp

    <div
        class="mx-2 bg-white dark:bg-gray-800 p-6 shadow-md rounded-tl-md rounded-tr-md overflow-scroll custom-scrollbar scrollbar-hide">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6 flex items-center"><span
                class="material-icons text-4xl font-extrabold">add</span> Add New Product</h2>

        <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Product Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                <input type="text" name="name"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('name') }}">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- SLUG -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SLUG</label>
                <input type="text" name="slug"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('slug') }}">
                @error('slug')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>


            <!-- SKU -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                <input type="text" name="sku"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('sku') }}">
                @error('sku')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                <textarea class="summernote-editor" name="description">{!! old('description', '') !!}</textarea>
                @error('description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Category -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                <select name="category_id"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                    <option value="">Select Category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Collection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Collection (Optional)</label>
                <select name="collection_id"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                    <option value="">Select Collection</option>
                    @foreach ($collections as $collection)
                        <option value="{{ $collection->id }}" @selected(old('collection_id') == $collection->id)>{{ $collection->name }}
                        </option>
                    @endforeach
                </select>
                @error('collection_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Price and Discount -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                    <input type="number" name="price" step="0.01"
                        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                        value="{{ old('price') }}">
                    @error('price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Price</label>
                    <input type="number" name="discount_price" step="0.01"
                        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                        value="{{ old('discount_price') }}">
                    @error('discount_price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Quantity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity in Stock</label>
                <input type="number" name="stock_quantity"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('stock_quantity') }}">
                @error('stock_quantity')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <!-- Status -->
            <div class="flex items-center">
                <label class="mr-4 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="sr-only peer"
                        {{ old('status') ? 'checked' : '' }}>
                    <div
                        class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer dark:bg-gray-600 peer-checked:bg-green-500">
                    </div>
                </label>
            </div>

            <div x-data="productComposer()" class="space-y-8">

                {{-- ========== Sizes ========== --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sizes</label>

                    <div class="mt-2 flex gap-2">
                        <input type="text" x-model="sizeInput" placeholder="e.g., XS, S, M"
                            @keydown.enter.prevent="addSize()"
                            class="w-full p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                        <button type="button" @click="addSize()"
                            class="px-4 py-2 bg-black text-white rounded-md">Add</button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="(s, i) in sizes" :key="s + i">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700">
                                <span x-text="s" class="text-sm dark:text-white"></span>
                                <button type="button" @click="removeSize(i)"
                                    class="text-gray-500 hover:text-red-600">&times;</button>
                                <input type="hidden" name="sizes[]" :value="s">
                            </div>
                        </template>
                    </div>
                    @error('sizes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ========== Colors (master list) ========== --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Colors</label>

                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" x-model="colorName" placeholder="Color name"
                            class="p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                        <input type="color" x-model="colorHex"
                            class="p-2 h-10 border rounded-md bg-white dark:bg-gray-700">
                        <button type="button" @click="addColor()"
                            class="px-4 py-2 bg-black text-white rounded-md">Add</button>
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <template x-for="(c, i) in colors" :key="c.name + c.hex + i">
                            <div
                                class="flex items-center justify-between gap-3 p-2 border rounded-lg bg-gray-50 dark:bg-gray-700">
                                <div class="flex items-center gap-3">
                                    <span class="inline-block w-6 h-6 rounded-full border"
                                        :style="`background:${c.hex}`"></span>
                                    <div class="text-sm dark:text-white">
                                        <div x-text="c.name"></div>
                                        <div class="text-gray-500" x-text="c.hex"></div>
                                    </div>
                                </div>

                                <!-- IMPORTANT: both fields share the SAME i -->
                                <input type="hidden" :name="'colors[' + i + '][name]'" :value="c.name">
                                <input type="hidden" :name="'colors[' + i + '][color_code]'" :value="c.hex">

                                <button type="button" @click="removeColor(i)"
                                    class="text-gray-500 hover:text-red-600">&times;</button>
                            </div>
                        </template>

                    </div>
                    @error('colors')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>


                {{-- ========== Images with color select ========== --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Images</label>

                    {{-- File input (hidden) --}}
                    <input name="images[]" type="file" multiple x-ref="fileInput"
                        @change="handleFiles($event.target.files)" class="hidden">

                    {{-- Thumbnail id --}}
                    <input type="hidden" name="thumbnail_id" :value="thumbnailId">

                    {{-- Drop area --}}
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 rounded-lg cursor-pointer
                bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition text-center
                text-gray-600 dark:text-gray-300"
                        @click="$refs.fileInput.click()" @dragover.prevent
                        @drop.prevent="handleFiles($event.dataTransfer.files)">
                        Click or drag files here to upload
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                        <template x-for="(img, idx) in images" :key="img.uid">
                            <div class="relative group border rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                {{-- Thumb radio --}}
                                <div
                                    class="absolute top-3 left-3 z-10 flex items-center gap-2 bg-white/80 dark:bg-gray-900/70 px-2 py-1 rounded-full">
                                    <input type="radio" :value="img.uid" x-model="thumbnailId"
                                        @change="setThumbnail(img)" class="form-radio text-blue-500 focus:ring-blue-400"
                                        title="Set as thumbnail">
                                    <span class="text-xs text-gray-700 dark:text-gray-200">Thumb</span>
                                </div>

                                <img :src="img.preview" class="w-full h-40 object-cover" alt="Image preview">

                                <div class="p-3 space-y-2">
                                    {{-- Select from colors added above --}}
                                    <div>
                                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Assign
                                            color</label>
                                        <select x-model="img.selectedColorIndex" @change="applySelectedColor(img)"
                                            class="w-full px-2 py-1.5 rounded-md border bg-white dark:bg-gray-700 dark:text-white">
                                            <option value="">— Select color —</option>
                                            <template x-for="(c,i) in colors" :key="c.name + c.hex + i">
                                                <option :value="i" x-text="`${c.name} (${c.hex})`"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-3" x-show="img.colorName">
                                        <span class="inline-block w-6 h-6 rounded-full border"
                                            :style="`background:${img.colorHex}`"></span>
                                        <div class="text-xs text-gray-600 dark:text-gray-300">
                                            <span x-text="img.colorName"></span>
                                            <span class="text-gray-400" x-text="img.colorHex"></span>
                                        </div>
                                    </div>

                                    {{-- Hidden inputs aligned with images[] --}}
                                    <input type="hidden" name="image_color_names[]" :value="img.colorName || ''">
                                    <input type="hidden" name="image_color_hexes[]" :value="img.colorHex || ''">
                                </div>

                                {{-- Delete --}}
                                <button type="button" @click.stop="deleteImage(idx)"
                                    class="absolute top-3 right-3 bg-white/80 dark:bg-gray-900/70 hover:bg-white text-red-600
                         rounded-full p-1.5 shadow">
                                    <span class="material-icons text-[18px]">delete</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>


            <!-- Submit -->
            <div>
                <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Save Product</button>
            </div>
        </form>
    </div>
    <script>
        console.log(@json($bootColors))

        function productComposer() {
            return {
                // sizes
                sizes: @json($bootSizes),
                sizeInput: '',
                addSize() {
                    const v = (this.sizeInput || '').trim();
                    if (v && !this.sizes.includes(v)) this.sizes.push(v);
                    this.sizeInput = '';
                },
                removeSize(i) {
                    this.sizes.splice(i, 1);
                },
                colors: @json($bootColors),
                colorName: '',
                colorHex: '#000000',
                addColor() {
                    const name = (this.colorName || '').trim();
                    const hex = (this.colorHex || '').trim();
                    if (!name || !hex) return;
                    this.colors.push({
                        name,
                        hex
                    });
                    this.colorName = '';
                    this.colorHex = '#000000';
                },
                removeColor(i) {
                    this.colors.splice(i, 1);
                },

                // images
                images: [], // { uid, file, preview, selectedColorIndex:'', colorName:'', colorHex:'' }
                thumbnailId: null,
                dt: new DataTransfer(),

                handleFiles(fileList) {
                    for (const file of fileList) {
                        const uid = `${Date.now()}_${Math.random().toString(36).slice(2)}`;
                        const reader = new FileReader();
                        reader.onload = e => {
                            this.images.push({
                                uid,
                                file,
                                preview: e.target.result,
                                selectedColorIndex: '',
                                colorName: '',
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
                    if (this.thumbnailId === removed?.uid) {
                        this.thumbnailId = this.images[0]?.uid || null;
                    }
                },

                setThumbnail(img) {
                    this.thumbnailId = img.uid;
                },

                applySelectedColor(img) {
                    const i = img.selectedColorIndex;
                    if (i === '' || this.colors[i] == null) {
                        img.colorName = '';
                        img.colorHex = '';
                        return;
                    }
                    img.colorName = this.colors[i].name;
                    img.colorHex = this.colors[i].hex;
                },
            }
        }
    </script>

@endsection
