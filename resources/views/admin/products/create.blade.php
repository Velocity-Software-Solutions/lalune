@extends('layouts.admin')

@section('title', 'Add Product')

@section('content')
    <div
        class="mx-2 bg-white dark:bg-gray-800 p-6 shadow-md rounded-tl-md rounded-tr-md overflow-scroll custom-scrollbar scrollbar-hide">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">➕ Add New Product</h2>

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

            <!-- Product Name In Arabic -->

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name In Arabic (optional)</label>
                <input type="text" name="name_ar"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('name_ar') }}">
                @error('name_ar')
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
                <textarea name="description" rows="4"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

                        <!-- Description Arabic -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Arabic Description (optional)</label>
                <textarea name="description_ar" rows="4"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">{{ old('description_ar') }}</textarea>
                @error('description_ar')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Category -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                <select name="category_id"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                    <option value="">-- Select Category --</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
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

            <!-- Condition -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
                <select name="condition"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white">
                    <option value="">-- Select Condition --</option>
                    <option value="new" @selected(old('condition') == 'new')>New</option>
                    <option value="used" @selected(old('condition') == 'used')>Used</option>
                    <option value="antique" @selected(old('condition') == 'antique')>Antique</option>
                </select>
                @error('condition')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Tags -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tags</label>
                <input type="text" name="tags" placeholder="e.g., vintage, silver, handmade"
                    class="w-full mt-1 p-2 border rounded-md bg-white dark:bg-gray-700 dark:text-white"
                    value="{{ old('tags') }}">
                @error('tags')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Images -->
            <div x-data="imagesUploader({
                images: [],
                initialThumbnailId: null
            })" class="space-y-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Images</label>

                <!-- Hidden file input -->
                <input name="images[]" type="file" multiple x-ref="fileInput" @change="handleFiles($event.target.files)"
                    class="hidden">

                <!-- Hidden thumbnail field -->
                <input type="hidden" name="thumbnail_id" :value="thumbnailId">

                <!-- Upload area -->
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition text-center text-gray-600 dark:text-gray-300"
                    @click="$refs.fileInput.click()" @dragover.prevent
                    @drop.prevent="handleFiles($event.dataTransfer.files)">
                    Click or drag files here to upload
                </div>

                <!-- Error -->
                @error('images')
                    <p class="text-red-500 text-sm">{{ $message }}</p>
                @enderror

                <!-- Image Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <template x-for="img in images" :key="img.id || img.tempId">
                        <div class="relative group">
                            <!-- Thumbnail selector -->
                            <div class="absolute top-4 left-4 z-10">
                                <input type="radio" :value="img.id || img.tempId" x-model="thumbnailId"
                                    @change="setThumbnail(img)" class="form-radio text-blue-500 focus:ring-blue-400"
                                    title="Set as thumbnail">
                            </div>

                            <!-- Image -->
                            <img :src="img.preview || ('/storage/' + img.image_path)"
                                class="w-full h-40 object-cover rounded-lg transition-transform duration-300 group-hover:scale-95"
                                alt="Image">

                            <!-- Delete button -->
                            <button @click.stop="deleteImage(img)"
                                class="flex justify-center items-center absolute top-4 right-4 hover:bg-red-300 text-white p-1 rounded-full shadow duration-300"
                                title="Delete">
                                <span
                                    class="transition duration-75 material-symbols-outlined text-red-400 group-hover:text-red-600">
                                    delete
                                </span>
                            </button>
                        </div>
                    </template>
                </div>
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

            <!-- Submit -->
            <div>
                <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Save Product</button>
            </div>
        </form>
    </div>
    <script>
        function imagesUploader({
            images = [],
            initialThumbnailId = null
        }) {
            return {
                images: [...images],
                thumbnailId: initialThumbnailId,

                handleFiles(fileList) {
                    for (const file of fileList) {
                        const reader = new FileReader();
                        const tempId = `temp_${this.images.length}`;

                        reader.onload = (e) => {
                            this.images.push({
                                file,
                                preview: e.target.result,
                                tempId,
                                isThumbnail: false
                            });

                            // Set first new uploaded image as thumbnail if none selected
                            if (!this.thumbnailId) {
                                this.thumbnailId = tempId;
                            }
                        };

                        reader.readAsDataURL(file);
                    }
                },

                async deleteImage(img) {
                    if (img.preview) {
                        this.images = this.images.filter(i => i !== img);
                        if (this.thumbnailId === img.tempId) {
                            this.thumbnailId = null;
                        }
                        return;
                    }

                    if (!confirm('Are you sure you want to delete this image?')) return;

                    try {
                        const response = await axios.post('{{ route('admin.product.image.delete') }}', {
                            id: img.id
                        }, {
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        if (response.data.status === 'success') {
                            this.images = this.images.filter(i => i.id !== img.id);
                            if (this.thumbnailId === img.id) {
                                this.thumbnailId = null;
                            }
                        } else {
                            alert('Failed to delete image');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Error deleting image.');
                    }
                },
                setThumbnail(selectedImg) {
                    this.thumbnailId = selectedImg.id || selectedImg.tempId;
                    this.images.forEach(img => {
                        img.isThumbnail = (img === selectedImg);
                    });
                }

            }
        }
    </script>
@endsection
