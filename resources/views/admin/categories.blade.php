@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
    <div class="max-w-6xl p-6 mx-2 h-full space-y-6 bg-white rounded-md shadow-md w-9/10 dark:bg-gray-800"
        x-data="{ showNewRow: false, showError: {{ $errors->has('name') ? 'true' : 'false' }} }">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Categories</h2>
            <button @click="showNewRow = true" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
                + Add Category
            </button>
        </div>

        {{-- Success Message --}}
        @if (session('success'))
            <div class="px-4 py-2 mb-4 text-green-700 bg-green-100 rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- Error Pop-up --}}
        <div x-show="showError" x-transition
            class="fixed z-50 w-full max-w-md px-4 py-3 transform -translate-x-1/2 bg-red-100 border border-red-300 rounded shadow top-10 left-1/2">
            <p class="mb-2 font-semibold text-red-700">Please fix the following:</p>
            <ul class="text-sm text-red-600 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button class="mt-2 text-xs text-red-700 underline" @click="showError = false">Dismiss</button>
        </div>

        {{-- Table Wrapper --}}
        <div class="overflow-x-auto border border-gray-200 rounded-md dark:border-gray-700">
            <table class="min-w-full text-sm table-auto">
                <thead class="text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-white">
                    <tr>
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">Category Name</th>
                        <th class="px-5 py-3 text-left">Arabic Name</th>
                        <th class="px-5 py-3 text-left">Slug</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">

                    {{-- New Row --}}


                    <tr x-show="showNewRow || showError" class="bg-gray-50 dark:bg-gray-900">
                        <form action="{{ route('admin.categories.store') }}" method="POST"
                            class="flex items-center w-full">
                            @csrf
                            <td class="px-5 py-2 text-gray-700 dark:text-white">New</td>
                            <td class="w-auto px-5 py-2">
                                <input name="name" placeholder="Enter category name" value="{{ old('name') }}"
                                    class="w-44 text-sm rounded-md form-input" />

                                @error('name')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </td>

                            <td class="w-auto px-5 py-2">
                                <input name="name_ar" placeholder="Enter category name in arabic"
                                    value="{{ old('name_ar') }}" class="w-44 text-sm rounded-md form-input" />

                                @error('name_ar')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="w-auto px-5 py-2">
                                <input name="slug" placeholder="Enter category slug" value="{{ old('slug') }}"
                                    class="w-44 text-sm rounded-md form-input" />

                                @error('slug')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </td>

                            <td class="px-5 py-2 text-center">
                                <button type="submit"
                                    class="flex items-center justify-center px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded hover:bg-green-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <path d="M8 12h8M12 8v8" />
                                    </svg>
                                    Add
                                </button>
                            </td>
                        </form>
                    </tr>


                    {{-- Existing Categories --}}
                    @foreach ($categories as $category)
                        <form action="{{ route('admin.categories.update', $category->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <tr>
                                <td class="px-5 py-2 text-gray-700 dark:text-white">{{ $loop->iteration }}</td>
                                <td class="w-auto px-5 py-2">
                                    <input name="name_{{ $category->id }}"
                                        value="{{ old('name_' . $category->id, $category->name) }}"
                                        class="w-44 text-sm rounded-md form-input" />
                                    @error('name_' . $category->id)
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="w-auto px-5 py-2">
                                    <input name="name_ar_{{ $category->id }}"
                                        value="{{ old('name_ar_' . $category->id, $category->name_ar) }}"
                                        class="w-44 text-sm rounded-md form-input" />
                                    @error('name_ar_' . $category->id)
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="w-auto px-5 py-2">
                                    <input name="slug_{{ $category->id }}"
                                        value="{{ old('slug_' . $category->id, $category->slug) }}"
                                        class="w-44 text-sm rounded-md form-input" />
                                    @error('slug_' . $category->id)
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="flex justify-center px-5 py-2 space-x-1">
                                    <button type="submit"
                                        class="px-2 py-1 text-xs text-white bg-blue-500 rounded hover:bg-blue-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path
                                                d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />
                                            <path d="m15 5 4 4" />
                                        </svg>
                                    </button>
                        </form>
                        <form action="{{ route('admin.categories.destroy', $category->id) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to delete this category?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2 py-1 text-xs text-white bg-red-500 rounded hover:bg-red-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                </svg>
                            </button>
                        </form>
                        </td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex justify-end mt-6">
            {{ $categories->links('pagination::tailwind') }}
        </div>
    </div>
@endsection
