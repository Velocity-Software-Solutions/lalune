@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

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
        @if (session('success'))
            <div class="mb-4 text-green-600">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2">
            <div>
                <h3 class="mb-1 font-semibold">Customer</h3>
                <p>{{ $order->user->name ?? 'Guest' }}</p>
                <p class="text-sm text-gray-500">{{ $order->user->email ?? '' }}</p>
            </div>
            <div>
                <h3 class="mb-1 font-semibold">Shipping Address</h3>
                <p class="whitespace-pre-line">{{ $order->shipping_address }}</p>
            </div>
            <div>
                <h3 class="mb-1 font-semibold">Order Status</h3>
                <p class="capitalize">{{ $order->order_status }}</p>
            </div>
            <div>
                <h3 class="mb-1 font-semibold">Payment Status</h3>
                <p class="capitalize">{{ $order->payment_status }}</p>
            </div>
            <div>
                <h3 class="mb-1 font-semibold">Shipping Method</h3>
                @if ($order->shippingOption)
                    <p>{{ $order->shippingOption->name }} – {{ $order->shippingOption->delivery_time }}</p>
                    <p class="text-sm text-gray-500">$ {{ number_format($order->shippingOption->price, 2) }}</p>
                @else
                    <p class="text-gray-500">(none)</p>
                @endif
            </div>
            @if ($order->coupon)
                <div>
                    <h3 class="mb-1 font-semibold">Coupon</h3>
                    <p>{{ $order->coupon->code }}</p>
                </div>
            @endif
        </div>

        <h3 class="mb-2 font-semibold">Items</h3>
        <div class="mb-6 overflow-x-auto">
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
                                    @if ($item->product && $item->product->image)
                                        <img src="{{ asset('storage/products/' . $item->product->image) }}"
                                            alt="{{ $item->product->name }}" class="object-cover w-10 h-10 rounded" />
                                    @endif
                                    <div>
                                        <div class="font-semibold">
                                            {{ $item->product->name ?? $item->product_name }}
                                        </div>
                                        <input type="hidden" name="items[{{ $i }}][id]"
                                            value="{{ $item->id }}">
                                    </div>
                                </div>
                            </td>
                            <td class="p-2 text-right">

                                ${{ number_format($item->quantity, 2) }}
                            </td>
                            <td class="p-2 text-right">
                                ${{ number_format($item->price, 2) }}
                            </td>
                            <td class="p-2 text-right">
                                ${{ number_format($item->subtotal, 2) }}
                            </td>
                        </tr>
                    @endforeach

                    <tr class="font-semibold bg-gray-50 dark:bg-gray-700">
                        <td colspan="3" class="p-2 text-right">
                            Shipping ({{ $order->shippingOption->name ?? 'N/A' }})
                        </td>
                        <td class="p-2 text-right">
                            ${{ number_format($order->shippingOption->price ?? 0, 2) }}
                        </td>
                    </tr>
                </tbody>


                <tfoot>
                    <tr class="font-semibold bg-gray-50 dark:bg-gray-700">
                        <td colspan="3" class="p-2 text-right">Total</td>
                        <td class="p-2 text-right">${{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex justify-end space-x-2">
            <a href="{{ route('admin.orders.edit', $order) }}"
                class="px-4 py-2 text-white bg-green-600 rounded hover:bg-green-700">Edit</a>
            <form action="{{ route('admin.orders.destroy', $order) }}" method="POST"
                onsubmit="return confirm('Delete this order?');">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 text-white bg-red-600 rounded hover:bg-red-700">Delete</button>
            </form>
        </div>
    </div>
@endsection
