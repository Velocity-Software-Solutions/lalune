@extends('layouts.app')

@section('content')
<div class="max-w-3xl px-6 py-10 mx-auto bg-white rounded shadow">
    <h1 class="mb-6 text-3xl font-bold text-green-700">{{ __('order.title_confirmed') }}</h1>

    <p class="mb-4 text-lg">{{ __('order.thank_you') }}</p>
    <p class="mb-1 font-semibold text-gray-700">{{ __('order.order_number') }}</p>
    <p class="mb-4 font-mono text-xl text-gray-700">{{ $order->order_number }}</p>

    <p class="mb-6">
        {!! __('order.confirmation_email', [
            'email' => '<strong>'.($order->user->email ?? __('order.your_email')).'</strong>'
        ]) !!}
    </p>

    <div class="p-4 mb-6 bg-gray-100 border rounded">
        <p><strong>{{ __('order.shipping') }}:</strong> {{ $order->shipping_address }}</p>
        <p><strong>{{ __('order.billing') }}:</strong> {{ $order->billing_address }}</p>
        <p><strong>{{ __('order.shipping_method') }}:</strong> {{ $order->shippingOption->name ?? __('order.na') }}</p>
        <p><strong>{{ __('order.total_paid') }}:</strong>
            {{ __('product.currency_aed') }} {{ number_format($order->total_amount, 2) }}
        </p>
    </div>

    <form action="{{ route('checkout.receipt', $order->id) }}" method="GET" target="_blank" class="inline">
        <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded">
            {{ __('order.download_receipt') }}
        </button>
    </form>

    <a href="{{ route('home') }}" class="ml-4 text-blue-600 underline">{{ __('order.back_to_shop') }}</a>
</div>
@endsection
