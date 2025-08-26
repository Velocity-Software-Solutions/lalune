@extends('layouts.admin')

@section('title', 'Products')

@section('content')
    <div class="p-6 mx-2 h-full bg-white dark:bg-gray-800 rounded-lg shadow">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">📦 Products</h2>
        <div class="flex justify-end mb-4">
            <a href="{{ route('admin.products.create') }}"
                class="inline-block px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                + Add New Product
            </a>
        </div>

        <table class="min-w-full table-auto border-collapse rounded overflow-hidden shadow-sm bg-white dark:bg-gray-900">
            <thead class="bg-gray-100 dark:bg-gray-700 text-sm font-bold text-gray-700 dark:text-white">
                <tr>
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Name</th>
                    <th class="px-3 py-2 text-left">Arabic Name</th>
                    <th class="px-3 py-2 text-left">SKU</th>
                    <th class="px-3 py-2 text-left">Price</th>
                    <th class="px-3 py-2 text-left">Discount</th>
                    <th class="px-3 py-2 text-left">Stock</th>
                    <th class="px-3 py-2 text-left">Condition</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Category</th>
                    <th class="px-3 py-2 text-left">Tags</th>
                    <th class="px-3 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm text-gray-700 dark:text-gray-200">
                @forelse ($products as $product)
                    <tr class="border-b dark:border-gray-700">
                        <td class="px-3 py-2">{{ $loop->iteration }}</td>
                        <td class="px-3 py-2">{{ $product->name }}</td>
                        <td class="px-3 py-2 text-right">{{ $product->name_ar }}</td>
                        <td class="px-3 py-2">{{ $product->sku }}</td>
                        <td class="px-3 py-2">AED {{ number_format($product->price, 2) }}</td>
                        <td class="px-3 py-2">
                            @if ($product->discount_price)
                                AED {{ number_format($product->discount_price, 2) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $product->stock_quantity }}</td>
                        <td class="px-3 py-2 capitalize">{{ $product->condition }}</td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-block px-2 py-1 text-xs font-semibold rounded 
              {{ $product->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $product->status ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $product->category->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $product->tags ?? '—' }}</td>
                        <td class="px-3 py-2 flex space-x-2">
                            <a href="{{ route('admin.products.edit', $product->id) }}"
                                class="text-blue-600 hover:underline">Edit</a>
                            <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST"
                                onsubmit="return confirm('Delete this product?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">No products found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
@endsection
