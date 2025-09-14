<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where('status', 1)->with('category')->paginate(10); // or ->get()

        return view('admin.products.index', compact('products'));
    }



    public function create()
    {
        $categories = Category::where('status', 1)->latest()->get();
        $collections = Collection::where('status', 1)->latest()->get();
        return view('admin.products.create', compact('categories', 'collections'));
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string', // you’ll unique-ify after generating
            'sku' => 'required|string|unique:products,sku',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',

            // If you submit a matrix, product-level qty is optional
            'stock_quantity' => 'required_without:stock_matrix|integer|min:0',
            'status' => 'sometimes|boolean',
            'category_id' => 'required|exists:categories,id',
            'collection_id' => 'nullable|exists:collections,id',

            // sizes
            'sizes' => 'sometimes|array',
            'sizes.*' => 'string|max:50',

            // colors (master list for product)
            'colors' => 'sometimes|array',
            'colors.*.name' => 'required_with:colors|string|max:50',
            'colors.*.color_code' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'colors.*.hex' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],

            // images
            'images' => 'sometimes|array',
            'images.*' => 'nullable|image|max:4096', // 4 MB
            'image_color_codes' => 'sometimes|array',
            'image_color_codes.*' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'image_color_hexes' => 'sometimes|array',
            'image_color_hexes.*' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],

            // quantity matrix: stock_matrix[colorIndex|na][sizeIndex|na] = int
            'stock_matrix' => 'sometimes|array',
            'stock_matrix.*' => 'array',
            'stock_matrix.*.*' => 'integer|min:0',
        ];

        $messages = [
            // Basics
            'name.required' => 'Please enter a product name.',
            'name.max' => 'Product name can be up to :max characters.',
            'slug.string' => 'The slug must be plain text.',
            'sku.required' => 'Please enter a SKU.',
            'sku.unique' => 'This SKU is already in use. Please choose another SKU.',
            'price.required' => 'Please enter a price.',
            'price.numeric' => 'Price must be a number.',
            'discount_price.numeric' => 'Discount price must be a number.',

            // Stock
            'stock_quantity.required_without' => 'Enter a “Stock quantity” or fill the stock matrix below.',
            'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',

            // Status / Relations
            'status.boolean' => 'Status must be on or off.',
            'category_id.required' => 'Please choose a category.',
            'category_id.exists' => 'The selected category is not valid.',
            'collection_id.exists' => 'The selected collection is not valid.',

            // Sizes / Colors
            'sizes.array' => 'Sizes must be sent as a list.',
            'sizes.*.string' => 'Each size must be text.',
            'sizes.*.max' => 'Each size can be up to :max characters.',
            'colors.array' => 'Colors must be sent as a list.',
            'colors.*.name.required_with' => 'Every color needs a name.',
            'colors.*.name.max' => 'Color names can be up to :max characters.',
            'colors.*.color_code.regex' => 'Use a valid hex color like #FFCC00.',
            'colors.*.hex.regex' => 'Use a valid hex color like #FFCC00.',

            // Images
            'images.array' => 'Please select one or more images.',
            'images.*.image' => 'Each file must be an image (jpg, jpeg, png, gif, webp, avif).',
            'images.*.max' => 'Each image must be 4 MB or smaller.',
            'images.*.uploaded' => 'We couldn’t upload this image. Try a smaller file or a different format.',
            'image_color_codes.*.regex' => 'Image color must be a valid hex like #A1B2C3.',
            'image_color_hexes.*.regex' => 'Image color must be a valid hex like #A1B2C3.',

            // Stock matrix
            'stock_matrix.array' => 'The stock matrix must be a grid of numbers.',
            'stock_matrix.*.array' => 'Each row in the stock matrix must be a list of numbers.',
            'stock_matrix.*.*.integer' => 'Stock quantities in the matrix must be whole numbers.',
            'stock_matrix.*.*.min' => 'Stock quantities in the matrix cannot be negative.',
        ];

        $attributes = [
            'description_ar' => 'Arabic description',
            'discount_price' => 'discounted price',
            'stock_quantity' => 'stock quantity',
            'category_id' => 'category',
            'collection_id' => 'collection',

            'sizes.*' => 'size',
            'colors.*.name' => 'color name',
            'colors.*.color_code' => 'color hex code',
            'colors.*.hex' => 'color hex code',

            'images' => 'images',
            'images.*' => 'image',
            'image_color_codes.*' => 'image color hex',
            'image_color_hexes.*' => 'image color hex',

            'stock_matrix' => 'stock matrix',
            'stock_matrix.*.*' => 'stock quantity',
        ];

        $validated = $request->validate($rules, $messages, $attributes);

        return DB::transaction(function () use ($request, $validated) {

            // 1) Create product with a UNIQUE slug (from input or name)
            $baseSlug = Str::slug($validated['slug'] ?? $validated['name']);
            $slug = $baseSlug;
            $i = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$i}";
                $i++;
            }

            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'sku' => $validated['sku'],
                'description' => $validated['description'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'stock_quantity' => $validated['stock_quantity'] ?? 0, // will be replaced by matrix sum if provided
                'status' => $request->boolean('status') ? 1 : 0,
                'category_id' => $validated['category_id'],
                'collection_id' => $validated['collection_id'] ?? null,
            ]);

            // 2) Sizes -> keep index → id mapping aligned with request order
            $sizeIdByIndex = [];
            foreach ((array) $request->input('sizes', []) as $idx => $sizeName) {
                $sizeName = trim((string) $sizeName);
                if ($sizeName === '') {
                    $sizeIdByIndex[$idx] = null;
                    continue;
                }
                $size = $product->sizes()->create(['size' => $sizeName]);
                $sizeIdByIndex[$idx] = $size->id;
            }

            // 3) Colors -> keep index → id mapping aligned with request order
            $colorIdByIndex = [];
            foreach ((array) $request->input('colors', []) as $idx => $c) {
                $name = isset($c['name']) ? trim((string) $c['name']) : null;
                $code = $c['color_code'] ?? $c['hex'] ?? null;
                $code = $code ? strtoupper($code) : null;

                if (!$name || !$code) {
                    $colorIdByIndex[$idx] = null;
                    continue;
                }

                $row = $product->colors()->create([
                    'name' => $name,
                    'color_code' => $code,
                ]);
                $colorIdByIndex[$idx] = $row->id;
            }

            // 4) Images (color tagging optional)
            $files = (array) $request->file('images', []);
            $imageCodesPri = (array) $request->input('image_color_codes', []);
            $imageCodesAlt = (array) $request->input('image_color_hexes', []);
            foreach ($files as $i => $file) {
                if (!$file) {
                    continue;
                }
                $path = $file->store('products', 'public');
                $colorCode = $imageCodesPri[$i] ?? $imageCodesAlt[$i] ?? null;
                $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $product->name,
                    'color_code' => $colorCode ? strtoupper($colorCode) : null,
                    'thumbnail' => $i === 0,
                ]);
            }

            // 5) Stock matrix → product_stock rows
            $matrix = (array) $request->input('stock_matrix', []);
            $inserted = 0;
            $totalFromMatrix = 0;

            foreach ($matrix as $ciKey => $row) {
                if (!is_array($row)) {
                    continue;
                }

                // Resolve color id (nullable). 'na' means no color axis.
                $colorId = $ciKey === 'na' ? null :
                    ($colorIdByIndex[(int) $ciKey] ?? null);

                foreach ($row as $siKey => $qtyRaw) {
                    $qty = max(0, (int) $qtyRaw);

                    // Resolve size id (nullable). 'na' means no size axis.
                    $sizeId = $siKey === 'na' ? null :
                        ($sizeIdByIndex[(int) $siKey] ?? null);

                    // Skip if both axes are NA (no options). In that case, rely on product-level stock.
                    if (is_null($colorId) && is_null($sizeId)) {
                        continue;
                    }

                    // If both the referenced option rows were skipped/invalid, ignore this cell.
                    // (Keeps index alignment safe even if a color/size was filtered out above.)
                    if ($ciKey !== 'na' && is_null($colorId)) {
                        continue;
                    }
                    if ($siKey !== 'na' && is_null($sizeId)) {
                        continue;
                    }

                    // Only insert meaningful rows; you may store zeros too if you prefer explicit 0s
                    if ($qty === 0) {
                        continue;
                    }

                    // Create product_stock row (use model relation or DB::table)
                    $product->stock()->create([
                        'product_id' => $product->id,
                        'color_id' => $colorId, // nullable
                        'size_id' => $sizeId,  // nullable
                        'quantity_on_hand' => $qty,
                        // 'quantity_reserved' => 0, // if your table has it
                    ]);

                    $inserted++;
                    $totalFromMatrix += $qty;
                }
            }

            // 6) If we inserted any per-option stock rows, override product-level total
            if ($inserted > 0) {
                $product->update(['stock_quantity' => $totalFromMatrix]);
            }

            return redirect()
                ->route('admin.products.index')
                ->with('success', 'Product created successfully!');
        });
    }



    public function edit(Product $product)
    {
        $categories = Category::where('status', 1)->latest()->get();
        $collections = Collection::where('status', 1)->latest()->get();        // Ensure the product has images loaded
        $product->load(['images', 'colors', 'sizes', 'stock']);
        // return $product;
        return view('admin.products.edit', compact('product', 'categories', 'collections'));
    }


    public function update(Request $request, Product $product)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', Rule::unique('products', 'slug')->ignore($product->id)],
            'sku' => ['required', 'string', Rule::unique('products', 'sku')->ignore($product->id)],
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',

            // if you submit a per-option matrix, the single quantity can be omitted
            'stock_quantity' => 'required_without:stock_matrix|integer|min:0',

            'status' => 'sometimes|boolean',
            'category_id' => 'required|exists:categories,id',
            'collection_id' => 'nullable|exists:collections,id',

            // sizes/colors
            'sizes' => 'sometimes|array',
            'sizes.*' => 'string|max:50',
            'colors' => 'sometimes|array',
            'colors.*.name' => 'required_with:colors|string|max:50',
            'colors.*.color_code' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'colors.*.hex' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],

            // existing image color updates
            'image_existing' => 'sometimes|array',
            'image_existing.*.color_code' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],

            // new images
            'images' => 'sometimes|array',
            'images.*' => 'nullable|image|max:4096', // 4 MB per image
            'image_color_hexes' => 'sometimes|array',
            'image_color_hexes.*' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'new_image_uids' => 'sometimes|array',
            'new_image_uids.*' => 'nullable|string',

            // thumbnail selection
            'thumbnail_existing_id' => 'nullable|integer',
            'thumbnail_new_uid' => 'nullable|string',

            // quantity matrix
            'stock_matrix' => 'sometimes|array',
            'stock_matrix.*' => 'array',
            'stock_matrix.*.*' => 'integer|min:0',
        ];

        $messages = [
            // basics
            'name.required' => 'Please enter a product name.',
            'price.required' => 'Please enter a price.',
            'price.numeric' => 'Price must be a number.',
            'discount_price.numeric' => 'Discount price must be a number.',
            'slug.unique' => 'This slug is already in use. Please choose another.',
            'sku.required' => 'Please enter a SKU.',
            'sku.unique' => 'This SKU is already in use.',

            // stock
            'stock_quantity.required_without' => 'Enter a “Stock quantity” or fill the stock matrix below.',
            'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',

            // relationships
            'category_id.required' => 'Please choose a category.',
            'category_id.exists' => 'The selected category is not valid.',
            'collection_id.exists' => 'The selected collection is not valid.',

            // sizes/colors
            'sizes.array' => 'Sizes must be sent as a list.',
            'sizes.*.string' => 'Each size must be text.',
            'colors.array' => 'Colors must be sent as a list.',
            'colors.*.name.required_with' => 'Every color needs a name.',
            'colors.*.name.max' => 'Color names can be up to :max characters.',
            'colors.*.color_code.regex' => 'Use a valid hex color like #FFCC00.',
            'colors.*.hex.regex' => 'Use a valid hex color like #FFCC00.',

            // images (this is where the confusing message comes from)
            'images.array' => 'Please select one or more images.',
            'images.*.image' => 'Each file must be an image (jpg, jpeg, png, gif, webp, avif).',
            'images.*.max' => 'Each image must be 4 MB or smaller.',
            'images.*.uploaded' => 'We couldn’t upload this image. Try a smaller file, or a different format.',
            // (Sometimes Laravel keys it as images.0, images.1; the wildcard above covers them.)

            // image color hexes
            'image_existing.*.color_code.regex' => 'Image color must be a valid hex like #A1B2C3.',
            'image_color_hexes.*.regex' => 'Image color must be a valid hex like #A1B2C3.',

            // matrix
            'stock_matrix.array' => 'The stock matrix must be a grid of numbers.',
            'stock_matrix.*.array' => 'Each row in the stock matrix must be a list of numbers.',
            'stock_matrix.*.*.integer' => 'Stock quantities in the matrix must be whole numbers.',
            'stock_matrix.*.*.min' => 'Stock quantities in the matrix cannot be negative.',
        ];

        $attributes = [
            'description_ar' => 'Arabic description',
            'discount_price' => 'discounted price',
            'stock_quantity' => 'stock quantity',
            'category_id' => 'category',
            'collection_id' => 'collection',

            'sizes.*' => 'size',
            'colors.*.name' => 'color name',
            'colors.*.color_code' => 'color hex code',
            'colors.*.hex' => 'color hex code',

            'images' => 'images',
            'images.*' => 'image',
            'image_existing.*.color_code' => 'image color hex',
            'image_color_hexes.*' => 'image color hex',

            'thumbnail_existing_id' => 'thumbnail (existing image)',
            'thumbnail_new_uid' => 'thumbnail (new image)',

            'stock_matrix' => 'stock matrix',
            'stock_matrix.*.*' => 'stock quantity',
        ];

        $validated = $request->validate($rules, $messages, $attributes);
        DB::transaction(function () use ($request, $validated, $product) {

            /* -------- 1) Core product fields -------- */
            $slugInput = $validated['slug'] ?? $validated['name'];
            $product->update([
                'name' => $validated['name'],
                'slug' => Str::slug($slugInput),
                'sku' => $validated['sku'],
                'description' => $validated['description'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                // stock_quantity will be set again after processing the matrix (if provided)
                'stock_quantity' => $validated['stock_quantity'] ?? $product->stock_quantity,
                'status' => $request->boolean('status') ? 1 : 0,
                'category_id' => $validated['category_id'],
                'collection_id' => $validated['collection_id'] ?? null,
            ]);

            /* -------- 2) Replace Sizes (keeping input order -> index map) -------- */
            $product->sizes()->delete(); // cascades product_stock rows that reference size_id
            $sizeIdByIndex = [];
            foreach ((array) $request->input('sizes', []) as $idx => $sizeName) {
                $sizeName = trim((string) $sizeName);
                if ($sizeName === '') {
                    $sizeIdByIndex[$idx] = null;
                    continue;
                }
                $size = $product->sizes()->create(['size' => $sizeName]);
                $sizeIdByIndex[$idx] = $size->id;
            }

            /* -------- 3) Replace Colors (keeping input order -> index map) -------- */
            $product->colors()->delete(); // cascades product_stock rows that reference color_id
            $colorIdByIndex = [];
            foreach ((array) $request->input('colors', []) as $idx => $c) {
                $name = isset($c['name']) ? trim((string) $c['name']) : null;
                $code = $c['color_code'] ?? $c['hex'] ?? null;
                $code = $code ? strtoupper($code) : null;

                if (!$name || !$code) {
                    $colorIdByIndex[$idx] = null;
                    continue;
                }

                $row = $product->colors()->create([
                    'name' => $name,
                    'color_code' => $code,
                ]);
                $colorIdByIndex[$idx] = $row->id;
            }

            /* -------- 4) Images -------- */
            // existing color updates
            foreach ((array) $request->input('image_existing', []) as $imgId => $data) {
                $img = $product->images()->where('id', (int) $imgId)->first();
                if (!$img)
                    continue;
                $code = $data['color_code'] ?? null;
                $img->color_code = $code ? strtoupper($code) : null;
                $img->save();
            }

            // new images
            $files = (array) $request->file('images', []);
            $newColorHexes = (array) $request->input('image_color_hexes', []);
            $newUids = (array) $request->input('new_image_uids', []);
            $createdNewByUid = []; // uid => image id

            foreach ($files as $i => $file) {
                if (!$file)
                    continue;

                $path = $file->store('products', 'public');
                $colorCode = $newColorHexes[$i] ?? null;
                $colorCode = $colorCode ? strtoupper($colorCode) : null;

                $imgModel = $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $product->name,
                    'color_code' => $colorCode,
                    'thumbnail' => 0,
                ]);

                $uid = $newUids[$i] ?? null;
                if ($uid)
                    $createdNewByUid[$uid] = $imgModel->id;
            }

            // thumbnail selection
            $thumbExistingId = $request->input('thumbnail_existing_id');
            $thumbNewUid = $request->input('thumbnail_new_uid');

            if ($thumbExistingId || $thumbNewUid) {
                $product->images()->update(['thumbnail' => 0]);
                if ($thumbExistingId) {
                    $img = $product->images()->where('id', (int) $thumbExistingId)->first();
                    if ($img) {
                        $img->thumbnail = 1;
                        $img->save();
                    }
                } elseif ($thumbNewUid && isset($createdNewByUid[$thumbNewUid])) {
                    $imgId = $createdNewByUid[$thumbNewUid];
                    $img = $product->images()->where('id', $imgId)->first();
                    if ($img) {
                        $img->thumbnail = 1;
                        $img->save();
                    }
                }
            } else {
                if (!$product->images()->where('thumbnail', 1)->exists()) {
                    $first = $product->images()->first();
                    if ($first) {
                        $first->thumbnail = 1;
                        $first->save();
                    }
                }
            }

            /* -------- 5) Stock matrix -> product_stock rows -------- */

            // At this point, old product_stock rows are already deleted by FK cascades
            // (because we replaced colors/sizes). We'll recreate from the posted matrix.
            $matrix = (array) $request->input('stock_matrix', []);
            $hasMatrix = $request->has('stock_matrix');

            $totalFromMatrix = 0; // sum of all numbers in matrix (including zeros)

            foreach ($matrix as $ciKey => $row) {
                if (!is_array($row))
                    continue;

                $colorId = $ciKey === 'na' ? null : ($colorIdByIndex[(int) $ciKey] ?? null);

                foreach ($row as $siKey => $qtyRaw) {
                    $qty = max(0, (int) $qtyRaw);
                    $totalFromMatrix += $qty;

                    $sizeId = $siKey === 'na' ? null : ($sizeIdByIndex[(int) $siKey] ?? null);

                    // skip impossible combo (both axes NA) → use product-level quantity instead
                    if (is_null($colorId) && is_null($sizeId)) {
                        continue;
                    }
                    // axis provided but couldn't map (e.g., user removed that option)
                    if ($ciKey !== 'na' && is_null($colorId))
                        continue;
                    if ($siKey !== 'na' && is_null($sizeId))
                        continue;

                    // store only meaningful rows; zeros are implicit
                    if ($qty === 0)
                        continue;

                    $product->stock()->create([
                        'product_id' => $product->id,
                        'color_id' => $colorId, // nullable
                        'size_id' => $sizeId,  // nullable
                        'quantity_on_hand' => $qty,
                        // 'quantity_reserved' => 0,
                    ]);
                }
            }

            // If a matrix was posted, product-level stock should mirror its sum
            if ($hasMatrix) {
                $product->update(['stock_quantity' => $totalFromMatrix]);
            }
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product updated successfully!');
    }



    public function destroy(Product $product)
    {
        $product->status = 0;
        $product->save();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function deleteImage(Request $request)
    {
        $image = ProductImage::findOrFail($request->id);

        // Optionally: delete from storage
        Storage::delete($image->image_path);

        $image->delete();

        return response()->json(['status' => 'success']);
    }

}

