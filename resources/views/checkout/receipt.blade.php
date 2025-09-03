@extends('layouts.app')

@section('content')
@php
    $rtl = app()->getLocale() === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';

    // Safe getters
    $ship = (array) ($order->shipping_address_json ?? []);
    $bill = (array) ($order->billing_address_json ?? []);
    $currency = $order->currency ?? 'USD';

    $fmt = fn($cents) => number_format(($cents ?? 0) / 100, 2);
    $addr = fn($a) => collect([$a['line1'] ?? null, $a['line2'] ?? null, $a['city'] ?? null, $a['state'] ?? null, $a['postal_code'] ?? null, $a['country'] ?? null])
                        ->filter()->implode(', ');
    $badge = function($status) {
        return match($status) {
            'paid'       => 'bg-green-100 text-green-800 ring-1 ring-green-300',
            'pending'    => 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
            'failed'     => 'bg-red-100 text-red-800 ring-1 ring-red-300',
            'refunded'   => 'bg-blue-100 text-blue-800 ring-1 ring-blue-300',
            default      => 'bg-gray-100 text-gray-800 ring-1 ring-gray-300',
        };
    };
@endphp

<div class="min-h-screen bg-gray-50 py-10" dir="{{ $dir }}">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="montserrat-semibold text-2xl sm:text-3xl text-black">
          {{ __('receipt.heading', ['number' => $order->order_number]) }}
        </h1>
        <p class="text-sm text-gray-600 mt-1">
          {{ __('receipt.order_date') }}:
          <span class="font-medium">{{ $order->created_at->translatedFormat('F j, Y') }}</span>
        </p>
      </div>

      <div class="text-right">
        {{-- Optional: your logo --}}
        {{-- <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="h-10"> --}}
        <div class="flex items-center gap-2 justify-end">
          <span class="text-xs px-2 py-1 rounded-full {{ $badge($order->payment_status) }}">
            {{ ucfirst($order->payment_status) }}
          </span>
          <span class="text-xs px-2 py-1 rounded-full {{ $badge($order->order_status) }}">
            {{ ucfirst($order->order_status) }}
          </span>
        </div>
      </div>
    </div>

    {{-- Customer / Addresses --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-6">
      <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('receipt.customer') }}</h3>
        <p class="text-gray-800">{{ $order->full_name }}</p>
        <p class="text-gray-600 text-sm">{{ $order->email }}</p>
        @if($order->phone)
          <p class="text-gray-600 text-sm mt-1">{{ $order->phone }}</p>
        @endif
      </div>

      <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('receipt.shipping_address') }}</h3>
        <p class="text-gray-800 text-sm leading-6">
          {{ $addr($ship) ?: '—' }}
        </p>
      </div>

      <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('receipt.billing_address') }}</h3>
        <p class="text-gray-800 text-sm leading-6">
          {{ $addr($bill) ?: '—' }}
        </p>
      </div>
    </div>

    {{-- Items --}}
    <div class="bg-white rounded-2xl shadow mt-6 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-base font-semibold text-gray-900">{{ __('receipt.items') }}</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
          <thead class="bg-gray-50">
            <tr class="text-gray-600">
              <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_product') }}</th>
              <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_qty') }}</th>
              <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_price') }}</th>
              <th class="px-5 py-3 text-left font-medium">{{ __('receipt.th_subtotal') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            @foreach ($order->items as $item)
              <tr class="text-gray-900">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    {{-- Optional thumbnail if in snapshot --}}
                    @php $thumb = data_get($item->snapshot, 'image_url'); @endphp
                    @if($thumb)
                      <img src="{{ $thumb }}" alt="" class="w-10 h-10 rounded object-cover ring-1 ring-gray-200">
                    @endif
                    <div>
                      <div class="font-medium">
                        {{ $item->name ?? ($item->product->name ?? __('receipt.deleted_item')) }}
                      </div>
                      @if($item->sku)
                        <div class="text-xs text-gray-500">{{ __('receipt.sku') }}: {{ $item->sku }}</div>
                      @endif
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">{{ $item->quantity }}</td>
                <td class="px-5 py-4">{{ $currency }} {{ $fmt($item->unit_price_cents ?? ($item->price ?? 0)*100) }}</td>
                <td class="px-5 py-4">
                  {{ $currency }} {{ $fmt($item->total_cents ?? ($item->subtotal ?? 0)*100) }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Actions --}}
    <div class="mt-8 flex flex-wrap gap-3">
      <a href="{{ route('home') }}"
         class="inline-flex items-center px-4 py-2 rounded-md bg-black text-white hover:bg-black/90">
        {{ __('receipt.back_to_shop') }}
      </a>
      <button onclick="window.print()"
              class="inline-flex items-center px-4 py-2 rounded-md ring-1 ring-gray-300 text-gray-800 hover:bg-gray-100">
        {{ __('receipt.print') }}
      </button>
      {{-- Optional PDF route --}}
      {{-- <a href="{{ route('orders.pdf', $order) }}" class="inline-flex items-center px-4 py-2 rounded-md ring-1 ring-gray-300 text-gray-800 hover:bg-gray-100">PDF</a> --}}
    </div>

    {{-- Small footer note --}}
    <p class="text-xs text-gray-500 mt-6">
      {{ __('receipt.footer_note') }}
    </p>
  </div>
</div>
@endsection
