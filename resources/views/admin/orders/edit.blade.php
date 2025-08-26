@extends('layouts.admin')

@section('title', 'Edit Order ' . $order->order_number)

@section('content')


    <div class="h-full max-h-full p-5 mx-3 overflow-scroll bg-white rounded-md shadow-md dark:bg-gray-800 scroll scroll-m-0 custom-scroll">
        <h2 class="mb-6 text-3xl font-bold text-gray-800 dark:text-white">
            Edit Order #{{ $order->order_number }}
        </h2>

        <div class="flex items-center justify-between mb-6">
            <!-- Back Button -->
            <a href="{{ url()->previous() }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm transition hover:bg-gray-100 hover:text-black dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back
            </a>

            <!-- Order List Button -->
            <a href="{{ route('admin.orders.index') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg shadow-md transition hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"></path>
                </svg>
                Order List
            </a>
        </div>



        @if ($errors->any())
            <div class="p-4 mb-4 text-red-700 bg-red-100 rounded">
                <ul class="ml-5 text-sm list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.orders.update', $order) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block mb-1 text-sm font-medium">Shipping Address <span class="text-red-500">*</span></label>
                <textarea name="shipping_address" rows="3" class="w-full form-textarea" required>{{ old('shipping_address', $order->shipping_address) }}</textarea>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium">Billing Address</label>
                <textarea name="billing_address" rows="3" class="w-full form-textarea">{{ old('billing_address', $order->billing_address) }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block mb-1 text-sm font-medium">Order Status <span class="text-red-500">*</span></label>
                    <select name="order_status" class="w-full form-select" required>
                        @foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(old('order_status', $order->order_status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-medium">Payment Status <span class="text-red-500">*</span></label>
                    <select name="payment_status" class="w-full form-select" required>
                        @foreach (['pending', 'paid', 'failed'] as $pstatus)
                            <option value="{{ $pstatus }}" @selected(old('payment_status', $order->payment_status) === $pstatus)>{{ ucfirst($pstatus) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium">Shipping Option <span class="text-red-500">*</span></label>
                <select name="shipping_option_id" class="w-full form-select" required>
                    @foreach ($shippingOptions as $opt)
                        <option value="{{ $opt->id }}" @selected(old('shipping_option_id', $order->shipping_option_id) == $opt->id)>
                            {{ $opt->name }} ({{ $opt->delivery_time }})
                        </option>
                    @endforeach
                </select>

                @if ($order->shippingOption)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Cost: <strong>${{ number_format($order->shippingOption->price, 2) }}</strong>
                    </p>
                @endif
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium">Notes</label>
                <textarea name="notes" rows="2" class="w-full form-textarea">{{ old('notes', $order->notes) }}</textarea>
            </div>

            <div class="mt-8">
                <h3 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Ordered Items</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-gray-200 table-auto dark:border-gray-700">
                        <thead class="text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-white">
                            <tr>
                                <th class="p-2 text-left">Product</th>
                                <th class="p-2 text-right">Qty</th>
                                <th class="p-2 text-right">Price</th>
                                <th class="p-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $i => $item)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="p-2">
                                        <div class="flex items-center space-x-3">
                                            {{-- Show image if exists --}}
                                            @if ($item->product && $item->product->images->first())
                                                <img src="{{ asset('storage/products/' . $item->product->images->first()->filename) }}"
                                                    alt="{{ $item->product->name }}"
                                                    class="object-cover w-12 h-12 rounded" />
                                            @endif

                                            {{-- Show product name with a link --}}
                                            <div class="flex flex-col">
                                                @if ($item->product)
                                                    <a href="{{ route('admin.products.edit', $item->product->id) }}"
                                                        target="_blank" class="font-semibold text-blue-600 hover:underline">
                                                        {{ $item->product->name }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-400">(deleted product)</span>
                                                @endif

                                                {{-- Product selector --}}
                                                <select name="items[{{ $loop->index }}][product_id]"
                                                    class="mt-1 text-sm form-select">
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}" @selected($item->product_id == $product->id)>
                                                            {{ $product->name }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <input type="hidden" name="items[{{ $loop->index }}][id]"
                                                    value="{{ $item->id }}">
                                            </div>
                                        </div>
                                    </td>



                                    <td class="p-2 text-right">
                                        <input type="number" name="items[{{ $i }}][quantity]"
                                            value="{{ old("items.$i.quantity", $item->quantity) }}"
                                            class="w-20 text-right form-input" min="1">
                                    </td>
                                    <td class="p-2 text-right">
                                        <input type="number" step="0.01" name="items[{{ $i }}][price]"
                                            value="{{ old("items.$i.price", $item->price) }}"
                                            class="w-24 text-right form-input">
                                    </td>
                                    <td class="p-2 text-right">
                                        ${{ number_format($item->subtotal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold bg-gray-50 dark:bg-gray-700">
                                <td colspan="3" class="p-2 text-right">Shipping
                                    ({{ $order->shippingOption->name ?? 'N/A' }})</td>
                                <td class="p-2 text-right">
                                    ${{ number_format($order->shippingOption->price ?? 0, 2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-gray-200 dark:bg-gray-800">
                                <td colspan="3" class="p-2 text-right">Total</td>
                                <td class="p-2 text-right">${{ number_format($order->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>

            <div class="flex justify-end mt-6 space-x-2">
                <a href="{{ route('admin.orders.show', $order) }}"
                    class="px-4 py-2 text-gray-800 bg-gray-300 rounded dark:bg-gray-600 dark:text-white">Cancel</a>
                <button type="submit" class="px-4 py-2 text-white bg-green-600 rounded hover:bg-green-700">Save
                    Changes</button>
            </div>
        </form>
    </div>
@endsection
