@extends('layouts.admin')

@section('title', 'Edit Product')

@push('head')
    @vite(['resources/js/summernote.js'])

@endpush
@section('content')
    @php
        // ---------- Boot from old() or model ----------

        // Sizes
        $bootSizes = old('sizes');
        if (!is_array($bootSizes)) {
            $bootSizes = $product->sizes->pluck('size')->all();
        }

        // Colors (normalize to [{name, hex}])
        $bootColors = [];
        $oldColors = old('colors');
        if (is_array($oldColors)) {
            foreach ($oldColors as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $name = trim($c['name'] ?? '');
                $hex = strtoupper(trim($c['color_code'] ?? ($c['hex'] ?? '')));
                if ($name !== '') {
                    if (!preg_match('/^#([0-9A-F]{6})$/i', $hex)) {
                        $hex = '#000000';
                    }
                    $bootColors[] = ['name' => $name, 'hex' => $hex];
                }
            }
        } else {
            foreach ($product->colors as $c) {
                $hex = strtoupper($c->color_code ?? '#000000');
                if (!preg_match('/^#([0-9A-F]{6})$/i', $hex)) {
                    $hex = '#000000';
                }
                $bootColors[] = ['name' => $c->name, 'hex' => $hex];
            }
        }

        // Existing images payload for Alpine
        $existingImages = $product->images
            ->map(function ($img) {
                return [
                    'id' => $img->id,
                    'url' => asset('storage/' . $img->image_path),
                    'colorHex' => strtoupper($img->color_code ?? ''),
                    'isThumb' => (bool) $img->is_thumbnail,
                ];
            })
            ->values()
            ->all();

        $existingThumbId = optional($product->images->firstWhere('is_thumbnail', true))->id;
    @endphp
    <script>
        window.productEditor = function(init) {
            init = init || {};
            return {
                // sizes
                sizes: Array.isArray(init.sizes) ? init.sizes : [],
                sizeInput: '',
                addSize() {
                    const v = (this.sizeInput || '').trim();
                    if (v && !this.sizes.includes(v)) this.sizes.push(v);
                    this.sizeInput = '';
                },
                removeSize(i) {
                    this.sizes.splice(i, 1);
                },

                // colors
                colors: Array.isArray(init.colors) ? init.colors : [],
                colorName: '',
                colorHex: '#000000',
                addColor() {
                    const n = (this.colorName || '').trim(),
                        h = (this.colorHex || '').trim();
                    if (!n || !h) return;
                    this.colors.push({
                        name: n,
                        hex: h.toUpperCase()
                    });
                    this.colorName = '';
                    this.colorHex = '#000000';
                },
                removeColor(i) {
                    this.colors.splice(i, 1);
                },

                // existing images
                imagesExisting: (Array.isArray(init.imagesExisting) ? init.imagesExisting : []).map(img => ({
                    ...img, // id, url, colorHex, isThumb
                    selectedColorIndex: ''
                })),

                // new images
                imagesNew: [],
                dt: new DataTransfer(),

                // thumbnail
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
                    if (i === '' || this.colors[i] == null) {
                        img.colorHex = '';
                        return;
                    }
                    img.colorHex = this.colors[i].hex;
                },

                handleFiles(fileList) {
                    for (const file of fileList) {
                        const uid = `${Date.now()}_${Math.random().toString(36).slice(2)}`;
                        const reader = new FileReader();
                        reader.onload = e => {
                            this.imagesNew.push({
                                uid,
                                file,
                                preview: e.target.result,
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
                    if (i === '' || this.colors[i] == null) {
                        img.colorHex = '';
                        return;
                    }
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
                            body: JSON.stringify({
                                id: img.id
                            })
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

        <form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data"
            class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Product Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                <input type="text" name="name"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('name', $product->name) }}">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- SLUG --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SLUG</label>
                <input type="text" name="slug"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('slug', $product->slug) }}">
                @error('slug')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- SKU --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                <input type="text" name="sku"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('sku', $product->sku) }}">
                @error('sku')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea class="summernote-editor" name="description">{!! old('description', $product->description) !!}</textarea>
                @error('description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
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
                @error('category_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
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
                @error('collection_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Price & Discount --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                    <input type="number" name="price" step="0.01"
                        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                        value="{{ old('price', $product->price) }}">
                    @error('price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Price</label>
                    <input type="number" name="discount_price" step="0.01"
                        class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                        value="{{ old('discount_price', $product->discount_price) }}">
                    @error('discount_price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Stock --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity in Stock</label>
                <input type="number" name="stock_quantity"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('stock_quantity', $product->stock_quantity) }}">
                @error('stock_quantity')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Status --}}
            <div class="flex items-center">
                <label class="mr-4 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="sr-only peer"
                        {{ old('status', $product->status) ? 'checked' : '' }}>
                    <div
                        class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer dark:bg-gray-600 peer-checked:bg-green-500">
                    </div>
                </label>
            </div>

            {{-- ========= Alpine: sizes, colors, images ========= --}}
            <div x-data="productEditor(@js([
    'sizes' => $bootSizes,
    'colors' => $bootColors,
    'imagesExisting' => $existingImages,
    'existingThumbId' => $existingThumbId,
]))" x-cloak class="space-y-8">

                {{-- Sizes --}}
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

                {{-- Colors (master) --}}
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

                                {{-- Indexed hidden inputs --}}
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

                {{-- Existing Images (with color select) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Existing Images</label>

                    {{-- hidden thumbnail fields (one of these will be set) --}}
                    <input type="hidden" name="thumbnail_existing_id" x-model="thumbnailExistingId">
                    <input type="hidden" name="thumbnail_new_uid" x-model="thumbnailNewUid">

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-2">
                        <template x-for="(img, idx) in imagesExisting" :key="'ex_' + img.id">
                            <div class="relative group border rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                {{-- Thumb selector (existing) --}}
                                <div
                                    class="absolute top-3 left-3 z-10 flex items-center gap-2 bg-white/80 dark:bg-gray-900/70 px-2 py-1 rounded-full">
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
                                            <option :value="i" x-text="`${c.name} (${c.hex})`"
                                                :selected="img.colorHex === c.hex"></option>
                                        </template>
                                    </select>

                                    <div class="flex items-center gap-3" x-show="img.colorHex">
                                        <span class="inline-block w-6 h-6 rounded-full border"
                                            :style="`background:${img.colorHex}`"></span>
                                        <div class="text-xs text-gray-600 dark:text-gray-300" x-text="img.colorHex"></div>
                                    </div>

                                    {{-- Hidden indexed by image id --}}
                                    <input type="hidden" :name="'image_existing[' + img.id + '][color_code]'"
                                        :value="img.colorHex || ''">
                                </div>

                                {{-- Delete existing image (AJAX) --}}
                                <button type="button" @click="deleteExisting(img, idx)"
                                    class="absolute top-3 right-3 bg-white/80 dark:bg-gray-900/70 hover:bg-white text-red-600 rounded-full p-1.5 shadow">
                                    <span class="material-icons text-[18px]">delete</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- New Images (add more) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add New Images</label>

                    <input name="images[]" type="file" multiple x-ref="fileInput"
                        @change="handleFiles($event.target.files)" class="hidden">

                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 rounded-lg cursor-pointer
                    bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition text-center
                    text-gray-600 dark:text-gray-300"
                        @click="$refs.fileInput.click()" @dragover.prevent
                        @drop.prevent="handleFiles($event.dataTransfer.files)">
                        Click or drag files here to upload
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                        <template x-for="(img, idx) in imagesNew" :key="'new_' + img.uid">
                            <div class="relative group border rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                {{-- Thumb selector (new) --}}
                                <div
                                    class="absolute top-3 left-3 z-10 flex items-center gap-2 bg-white/80 dark:bg-gray-900/70 px-2 py-1 rounded-full">
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
                                        <span class="inline-block w-6 h-6 rounded-full border"
                                            :style="`background:${img.colorHex}`"></span>
                                        <div class="text-xs text-gray-600 dark:text-gray-300" x-text="img.colorHex"></div>
                                    </div>

                                    {{-- Hidden aligned with images[] (by order) --}}
                                    <input type="hidden" :name="'image_color_hexes[' + idx + ']'"
                                        :value="img.colorHex || ''">
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
