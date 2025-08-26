@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    <div
        class="h-full max-h-full p-5 mx-3 overflow-scroll bg-white rounded-md shadow-md dark:bg-gray-800 scroll scroll-m-0 custom-scroll">

        <div class="flex items-center justify-between mb-5">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Orders</h2>
            <a href="{{ route('admin.orders.create') }}"
                class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">+ New Order</a>
        </div>

        @if (session('success'))
            <div class="mb-4 text-green-600 dark:text-green-400">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 table-auto dark:border-gray-700">
                <thead class="text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-white">
                    <tr>
                        <th class="p-3 text-left">#</th>
                        <th class="p-3 text-left">Order #</th>
                        <th class="p-3 text-left">Customer</th>
                        <th class="p-3 text-left">Total</th>
                        <th class="p-3 text-left">Order Status</th>
                        <th class="p-3 text-left">Payment</th>
                        <th class="p-3 text-left">Date</th>
                        <th class="p-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($orders as $order)
                        <tr class="text-gray-800 border-t border-gray-200 dark:border-gray-600 dark:text-white">
                            <td class="p-3">{{ $loop->iteration }}</td>
                            <td class="p-3 font-mono">{{ $order->order_number }}</td>
                            <td class="p-3">{{ $order->user->name ?? 'Guest' }}</td>
                            <td class="p-3">${{ number_format($order->total_amount, 2) }}</td>
                            <td class="p-3 capitalize">{{ $order->order_status }}</td>
                            <td class="p-3 capitalize">{{ $order->payment_status }}</td>
                            <td class="p-3">{{ $order->created_at->format('Y-m-d') }}</td>
                            <td class="p-3 space-x-2 text-center">
                                <a href="{{ route('admin.orders.show', $order) }}"
                                    class="text-blue-600 hover:underline">View</a>
                                <a href="{{ route('admin.orders.edit', $order) }}"
                                    class="text-green-600 hover:underline">Edit</a>
                                <form action="{{ route('admin.orders.destroy', $order) }}" method="POST"
                                    class="inline" onsubmit="return confirm('Delete this order?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-4 text-center text-gray-500">No orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
@endsection
