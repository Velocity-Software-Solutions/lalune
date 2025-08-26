@extends('layouts.admin')

@section('title', 'Manage Coupons')

@section('content')
    <div class="max-w-6xl p-6 mx-2 h-full space-y-6 bg-white rounded-md shadow-md w-9/10 dark:bg-gray-800" x-data="{ showNewRow: false }">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Coupons</h2>
            <button @click="showNewRow = true" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
                + Add Coupon
            </button>
        </div>

        {{-- Success message --}}
        @if (session('success'))
            <div class="px-4 py-2 text-green-700 bg-green-100 rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- Table --}}
        <div class="overflow-x-auto border border-gray-200 rounded-md dark:border-gray-700">
            <table class="min-w-full table-auto border-collapse rounded overflow-hidden shadow-sm bg-white dark:bg-gray-900">
                <thead class="text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-white">
                    <tr>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">Code</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Value</th>
                        <th class="px-4 py-2">Min Order</th>
                        <th class="px-4 py-2">Limit</th>
                        <th class="px-4 py-2">Expires</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    {{-- Add Row --}}
                    <tr x-show="showNewRow" class="bg-gray-50 dark:bg-gray-900">
                        <form action="{{ route('admin.coupons.store') }}" method="POST" class="flex items-center ">
                            @csrf
                            <td class="px-4 py-2">New</td>
                            <td class="px-4 py-2"><input name="code" class="w-24 text-sm rounded-md form-input " /></td>
                            <td class="px-4 py-2">
                                <select name="discount_type" class="w-auto text-sm rounded-md form-select">
                                    <option value="fixed">Fixed</option>
                                    <option value="percentage">Percentage</option>
                                </select>
                            </td>
                            <td class="px-4 py-2"><input name="value" type="number" step="0.01"
                                    class="w-20 text-sm rounded-md form-input " /></td>
                            <td class="px-4 py-2"><input name="min_order_amount" type="number" step="0.01"
                                    class="w-20 text-sm rounded-md form-input " /></td>
                            <td class="px-4 py-2"><input name="usage_limit" type="number"
                                    class="w-20 text-sm rounded-md form-input " /></td>
                            <td class="px-4 py-2"><input name="expires_at" type="datetime-local"
                                    class="text-sm rounded-md form-input w-44" /></td>
                            <td class="px-4 py-2">
                                <select name="is_active" class="w-auto text-sm rounded-md form-select">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <button type="submit"
                                    class="flex items-center justify-center px-2 py-1 text-xs font-semibold text-white transition-all bg-green-500 rounded hover:bg-green-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M8 12h8M12 8v8"></path>
                                    </svg>
                                    Add
                                </button>
                            </td>
                        </form>
                    </tr>

                    {{-- Existing Coupons --}}
                    @foreach ($coupons as $coupon)
                        <tr>
                            <form action="{{ route('admin.coupons.update', $coupon->id) }}" method="POST"
                                class="flex items-center ">
                                @csrf
                                @method('PUT')
                                <td class="px-4 py-2">{{ $loop->iteration }}</td>
                                <td class="px-4 py-2"><input name="code" value="{{ $coupon->code }}"
                                        class="w-24 text-sm rounded-md form-input " /></td>
                                <td class="px-4 py-2">
                                    <select name="discount_type" class="w-auto text-sm rounded-md form-select">
                                        <option value="fixed" @selected($coupon->discount_type === 'fixed')>Fixed</option>
                                        <option value="percentage" @selected($coupon->discount_type === 'percentage')>Percentage</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2"><input name="value" value="{{ $coupon->value }}" type="number"
                                        step="0.01" class="w-20 text-sm rounded-md form-input" /></td>
                                <td class="px-4 py-2"><input name="min_order_amount" value="{{ $coupon->min_order_amount }}"
                                        type="number" step="0.01" class="w-20 text-sm rounded-md form-input" />
                                </td>
                                <td class="px-4 py-2"><input name="usage_limit" value="{{ $coupon->usage_limit }}"
                                        type="number" class="w-20 text-sm rounded-md form-input" /></td>
                                <td class="px-4 py-2"><input name="expires_at"
                                        value="{{ \Carbon\Carbon::parse($coupon->expires_at)->format('Y-m-d\TH:i') }}"
                                        type="datetime-local" class="text-sm rounded-md form-input w-44" />
                                </td>
                                <td class="px-4 py-2">
                                    <select name="is_active" class="w-auto text-sm rounded-md form-select">
                                        <option value="1" @selected($coupon->is_active)>Yes</option>
                                        <option value="0" @selected(!$coupon->is_active)>No</option>
                                    </select>
                                </td>
                                <td class="flex items-center justify-center px-4 py-2 space-x-1">
                                    <button type="submit"
                                        class="p-1 text-xs text-white transition-all bg-blue-500 rounded hover:bg-blue-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />
                                            <path d="m15 5 4 4" />
                                        </svg>
                                    </button>
                            </form>
                            <form action="{{ route('admin.coupons.destroy', $coupon->id) }}" method="POST"
                                onsubmit="return confirm('Are you sure?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="p-1 text-xs text-white transition-all bg-red-500 rounded hover:bg-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
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
        <div class="flex justify-end pt-4">
            {{ $coupons->links('pagination::tailwind') }}
        </div>
    </div>
@endsection
