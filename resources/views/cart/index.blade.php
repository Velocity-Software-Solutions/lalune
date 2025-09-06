@extends('layouts.app')

@section('content')
<div class="px-4 py-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
    <h1 class="mb-6 text-3xl font-bold text-black">{{ __('cart.title') }}</h1>

    <form action="{{ route('cart.applyCoupon') }}" class="py-4" method="POST">
        @csrf
        <label for="coupon" class="mr-2">{{ __('cart.have_coupon') }}</label>
        <input id="coupon" type="text" name="coupon_code" placeholder="{{ __('cart.coupon_placeholder') }}" class="p-2 border border-black/30 rounded focus:outline-none focus:ring-2 focus:ring-black/20 focus:border-black">
        <button type="submit" class="px-4 py-2 text-white bg-black hover:bg-black/90 focus:ring-2 focus:ring-black/40 transition rounded">{{ __('cart.apply') }}</button>
    </form>

    @if (session('coupon'))
        <p class="mt-2 text-green-600">
            {{ __('cart.coupon_applied') }}
            <strong>{{ session('coupon')->code }}</strong>
        </p>
    @endif

    @if (session('cart') && count(session('cart')) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow border border-white/60">
                <thead>
                    <tr class="text-sm font-semibold text-left text-black bg-white">
                        <th class="px-6 py-4">{{ __('cart.th_product') }}</th>
                        <th class="px-6 py-4">{{ __('cart.th_price') }}</th>
                        <th class="px-6 py-4">{{ __('cart.th_quantity') }}</th>
                        <th class="px-6 py-4">{{ __('cart.th_subtotal') }}</th>
                        <th class="px-6 py-4">{{ __('cart.th_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $total = 0; @endphp
                    @foreach (session('cart') as $id => $item)
                        @php
                            $subtotal = $item['price'] * $item['quantity'];
                            $total += $subtotal;
                        @endphp
                        <tr class="border-t">
                            <td class="flex items-center px-6 py-4 space-x-4">
                                <img src="/storage/{{ $item['image_path'] }}" class="object-cover w-12 h-12 rounded" alt="">
                                <span>{{ $item['name'] }}</span>
                            </td>
                            <td class="px-6 py-4">{{ __('product.currency_aed') }} {{ number_format($item['price'], 2) }}</td>
                            <td class="px-6 py-4">{{ $item['quantity'] }}</td>
                            <td class="px-6 py-4">{{ __('product.currency_aed') }} {{ number_format($subtotal, 2) }}</td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('cart.remove', $id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:underline">
                                        {{ __('cart.remove') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-8 text-right">
            <h3 class="text-2xl font-semibold text-black tracking-wide">
                {{ __('cart.total') }}: {{ __('product.currency_aed') }} {{ number_format($total, 2) }}
            </h3>
            <a href="{{ route('checkout.index') }}"
               class="inline-block px-6 py-2 mt-4 text-white transition bg-black hover:bg-black/90 focus:ring-2 focus:ring-black/40 rounded">
                {{ __('cart.proceed_checkout') }}
            </a>
        </div>
    @else
        <div class="text-gray-600 text-center">
            {{ __('cart.empty') }}
        </div>
    @endif
</div>
@endsection
