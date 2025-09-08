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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'sku' => 'required|string|unique:products,sku',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'status' => 'sometimes|boolean',
            'category_id' => 'required|exists:categories,id',
            'collection_id' => 'required|exists:collections,id',

            // sizes
            'sizes' => 'sometimes|array',
            'sizes.*' => 'string|max:50',

            // colors (master list for product)
            'colors' => 'sometimes|array',
            'colors.*.name' => 'required_with:colors|string|max:50',
            'colors.*.color_code' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'], // prefer this
            'colors.*.hex' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'], // fallback if front-end sends hex

            // images
            'images' => 'sometimes|array',
            'images.*' => 'nullable|image|max:4096',

            // per-image color mapping (aligned by index with images[])
            'image_color_codes' => 'sometimes|array',
            'image_color_codes.*' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'image_color_hexes' => 'sometimes|array',
            'image_color_hexes.*' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
        ]);

        return DB::transaction(function () use ($request, $validated) {

            // Create product (use provided slug or fallback to slugified name)
            $product = Product::create([
                'name' => $validated['name'],
                'slug' => !empty($validated['slug'])
                    ? Str::slug($validated['slug'])
                    : Str::slug($validated['name']),
                'sku' => $validated['sku'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'stock_quantity' => $validated['stock_quantity'],
                'status' => $request->boolean('status') ? 1 : 0,
                'category_id' => $validated['category_id'],
                'collection_id' => $validated['collection_id'],
            ]);

            /** -------------------------
             *  Sizes (rows: name)
             *  ------------------------- */
            foreach ((array) $request->input('sizes', []) as $sizeName) {
                $sizeName = trim((string) $sizeName);
                if ($sizeName !== '') {
                    $product->sizes()->create(['size' => $sizeName]);
                }
            }

            /** -------------------------
             *  Colors (rows: name, color_code)
             *  Accepts colors[][color_code] OR colors[][hex]
             *  ------------------------- */
            foreach ((array) $request->input('colors', []) as $c) {
                $name = isset($c['name']) ? trim((string) $c['name']) : null;
                $code = $c['color_code'] ?? $c['hex'] ?? null;
                if ($name && $code) {
                    $product->colors()->create([
                        'name' => $name,
                        'color_code' => strtoupper($code),
                    ]);
                }
            }

            /** -------------------------
             *  Images (with per-image color_code + thumbnail flag)
             *  Align arrays by index: images[] with image_color_codes[] OR image_color_hexes[]
             *  ------------------------- */
            $files = (array) $request->file('images', []);
            $imageCodesPref = (array) $request->input('image_color_codes', []);
            $imageCodesAlt = (array) $request->input('image_color_hexes', []);

            foreach ($files as $i => $file) {
                if (!$file) {
                    continue;
                }

                $path = $file->store('products', 'public');

                // prefer image_color_codes[], fall back to image_color_hexes[]
                $colorCode = $imageCodesPref[$i] ?? $imageCodesAlt[$i] ?? null;
                $colorCode = $colorCode ? strtoupper($colorCode) : null;

                $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $product->name,
                    'color_code' => $colorCode,      // nullable
                    'thumbnail' => $i === 0,        // mark first as thumbnail (simple default)
                ]);
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
        $product->load(['images', 'colors', 'sizes']);
        // return $product;
        return view('admin.products.edit', compact('product', 'categories', 'collections'));
    }


public function update(Request $request, Product $product)
{
    $validated = $request->validate([
        'name'            => 'required|string|max:255',
        'slug'            => ['nullable','string', Rule::unique('products','slug')->ignore($product->id)],
        'sku'             => ['required','string', Rule::unique('products','sku')->ignore($product->id)],
        'description'     => 'nullable|string',
        'description_ar'  => 'nullable|string',
        'price'           => 'required|numeric|min:0',
        'discount_price'  => 'nullable|numeric|min:0',
        'stock_quantity'  => 'required|integer|min:0',
        'status'          => 'sometimes|boolean',
        'category_id'     => 'required|exists:categories,id',
        'collection_id'   => 'required|exists:collections,id',

        // sizes
        'sizes'           => 'sometimes|array',
        'sizes.*'         => 'string|max:50',

        // colors (master list)
        'colors'                 => 'sometimes|array',
        'colors.*.name'          => 'required_with:colors|string|max:50',
        'colors.*.color_code'    => ['nullable','regex:/^#([0-9A-Fa-f]{6})$/'],
        'colors.*.hex'           => ['nullable','regex:/^#([0-9A-Fa-f]{6})$/'],

        // existing image color updates
        'image_existing'                 => 'sometimes|array',
        'image_existing.*.color_code'    => ['nullable','regex:/^#([0-9A-Fa-f]{6})$/'],

        // new images
        'images'                => 'sometimes|array',
        'images.*'              => 'nullable|image|max:4096',
        'image_color_hexes'     => 'sometimes|array',
        'image_color_hexes.*'   => ['nullable','regex:/^#([0-9A-Fa-f]{6})$/'],

        // used to identify which new file was chosen as thumbnail (aligned by index)
        'new_image_uids'        => 'sometimes|array',
        'new_image_uids.*'      => 'nullable|string',

        // thumbnail selection
        'thumbnail_existing_id' => 'nullable|integer',
        'thumbnail_new_uid'     => 'nullable|string',
    ]);

    DB::transaction(function () use ($request, $validated, $product) {

        // ---------- 1) Core fields ----------
        $product->update([
            'name'            => $validated['name'],
            'slug'            => !empty($validated['slug'])
                                    ? Str::slug($validated['slug'])
                                    : Str::slug($validated['name']),
            'sku'             => $validated['sku'],
            'description'     => $validated['description']     ?? null,
            'description_ar'  => $validated['description_ar']  ?? null,
            'price'           => $validated['price'],
            'discount_price'  => $validated['discount_price']  ?? null,
            'stock_quantity'  => $validated['stock_quantity'],
            'status'          => $request->boolean('status') ? 1 : 0,
            'category_id'     => $validated['category_id'],
            'collection_id'   => $validated['collection_id'],
        ]);

        // ---------- 2) Sizes: replace ----------
        $product->sizes()->delete();
        foreach ((array) $request->input('sizes', []) as $sizeName) {
            $sizeName = trim((string) $sizeName);
            if ($sizeName !== '') {
                $product->sizes()->create(['size' => $sizeName]);
            }
        }

        // ---------- 3) Colors: replace ----------
        $product->colors()->delete();
        foreach ((array) $request->input('colors', []) as $c) {
            $name = isset($c['name']) ? trim((string) $c['name']) : null;
            $code = $c['color_code'] ?? $c['hex'] ?? null;
            if ($name && $code) {
                $product->colors()->create([
                    'name'       => $name,
                    'color_code' => strtoupper($code),
                ]);
            }
        }

        // ---------- 4) Existing images: update color codes ----------
        foreach ((array) $request->input('image_existing', []) as $imgId => $data) {
            $img = $product->images()->where('id', (int)$imgId)->first();
            if (!$img) continue;

            $code = $data['color_code'] ?? null;
            $img->color_code = $code ? strtoupper($code) : null;
            $img->save();
        }

        // ---------- 5) New images: store & attach with color ----------
        $files           = (array) $request->file('images', []);
        $newColorHexes   = (array) $request->input('image_color_hexes', []);
        $newUids         = (array) $request->input('new_image_uids', []);
        $createdNewByUid = []; // uid => image model id

        foreach ($files as $i => $file) {
            if (!$file) continue;

            $path = $file->store('products', 'public');

            $colorCode = $newColorHexes[$i] ?? null;
            $colorCode = $colorCode ? strtoupper($colorCode) : null;

            $imgModel = $product->images()->create([
                'image_path'   => $path,
                'alt_text'     => $product->name,
                'color_code'   => $colorCode,
                'thumbnail' => 0, // set properly in step 6
            ]);

            // map the posted uid (same index) to the created image
            $uid = $newUids[$i] ?? null;
            if ($uid) {
                $createdNewByUid[$uid] = $imgModel->id;
            }
        }

        // ---------- 6) Thumbnail handling ----------
        $thumbExistingId = $request->input('thumbnail_existing_id');
        $thumbNewUid     = $request->input('thumbnail_new_uid');

        // If a specific thumbnail is chosen, reset all to 0 first
        if ($thumbExistingId || $thumbNewUid) {
            $product->images()->update(['thumbnail' => 0]);

            if ($thumbExistingId) {
                // prefer existing if provided
                $img = $product->images()->where('id', (int)$thumbExistingId)->first();
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
            // nothing chosen; ensure at least one thumbnail exists
            if (!$product->images()->where('thumbnail', 1)->exists()) {
                $first = $product->images()->first();
                if ($first) {
                    $first->thumbnail = 1;
                    $first->save();
                }
            }
        }

    }); // transaction

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

