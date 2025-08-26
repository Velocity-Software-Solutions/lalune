<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where('status', 1)->with('category')->latest()->paginate(10); // or ->get()

        return view('admin.products.index', compact('products'));
    }



    public function create()
    {
        $categories = Category::all();
        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        // Validate & store product
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'slug' => 'required|string|unique:products,slug',
            'sku' => 'required|string|unique:products,sku',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'stock_quantity' => 'required|integer|min:0',
            'condition' => 'required|in:new,used,antique',
            'status' => 'boolean',
            'category_id' => 'required|exists:categories,id',
            'tags' => 'nullable|string',
            'images.*' => 'nullable|image|max:2048'
        ]);

        // Create Product
        $product = Product::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
            'status' => $request->has('status') ? 1 : 0
        ]);

        // Save images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $product->name
                ]);
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully!');
    }


    public function edit(Product $product)
    {
        $categories = Category::all();
        // Ensure the product has images loaded
        $product->load('images');
        return view('admin.products.edit', compact('product', 'categories'));
    }


    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'slug' => 'required|string|unique:products,slug,' . $product->id,
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'stock_quantity' => 'required|integer|min:0',
            'condition' => 'required|in:new,used,antique',
            'status' => 'boolean',
            'category_id' => 'required|exists:categories,id',
            'tags' => 'nullable|string',
            'images.*' => 'nullable|image|max:8192'
        ]);

        $product->update([
            ...$validated,
            'slug' => Str::slug($validated['name']),
            'status' => $request->has('status') ? 1 : 0
        ]);

        // Upload new images
        if ($request->hasFile('images')) {
            $product->images()->update(['thumbnail' => 0]); // Reset thumbnails

            $uploadedImagesMap = []; // temp_0 => newImageID

            foreach ($request->file('images') as $index => $uploadedFile) {
                $path = $uploadedFile->store('products', 'public');

                $image = $product->images()->create([
                    'image_path' => $path,
                    'alt_text' => $product->name,
                    'thumbnail' => 0, // Set all to 0 first
                ]);

                // Store mapping of temp ID to real ID (to match Alpine)
                $uploadedImagesMap['temp_' . $index] = $image->id;
            }

            // Now update thumbnail

        }
        $product->images()->update(['thumbnail' => 0]); // Reset all

        if ($request->thumbnail_id) {
            $thumbnailId = $request->thumbnail_id;

            // Check if it's a temp ID (new upload)
            if (str_starts_with($thumbnailId, 'temp_')) {
                $realId = $uploadedImagesMap[$thumbnailId] ?? null;
                if ($realId) {
                    ProductImage::find($realId)->update(['thumbnail' => 1]);
                }
            } else {
                // It’s an existing image ID
                ProductImage::find($thumbnailId)->update(['thumbnail' => 1]);
            }
        }



        return redirect()->route('admin.products.edit', $product->id)->with('success', 'Product updated successfully!');
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

