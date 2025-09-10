@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
@php
  $ship = (array) ($order->shipping_address_json ?? []);
  $bill = (array) ($order->billing_address_json ?? []);
  $currency = $order->currency ?? 'USD';

  $fmt = fn($cents) => number_format(($cents ?? 0)/100, 2);

  $addr = function(array $a){
      return collect([
          $a['name'] ?? null,
          $a['line1'] ?? null,
          $a['line2'] ?? null,
          trim(($a['city'] ?? '').' '.($a['state'] ?? '')),
          $a['postal_code'] ?? null,
          $a['country'] ?? null,
          $a['email'] ?? null,
          $a['phone'] ?? null,
      ])->filter()->implode("\n");
  };

  $badge = function($v, $map){
      $classes = [
          'paid'       => 'bg-green-100 text-green-800 ring-1 ring-green-300',
          'pending'    => 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
          'failed'     => 'bg-red-100 text-red-800 ring-1 ring-red-300',
          'refunded'   => 'bg-blue-100 text-blue-800 ring-1 ring-blue-300',
          'processing' => 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-300',
          'shipped'    => 'bg-sky-100 text-sky-800 ring-1 ring-sky-300',
          'delivered'  => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300',
          'cancelled'  => 'bg-gray-200 text-gray-700 ring-1 ring-gray-300',
          'default'    => 'bg-gray-100 text-gray-800 ring-1 ring-gray-300',
      ];
      $key = $map[$v] ?? 'default';
      return $classes[$key] ?? $classes['default'];
  };
@endphp

<div class="h-full max-h-full p-6 mx-3 overflow-auto bg-white rounded-2xl shadow-md dark:bg-gray-800">

  {{-- Header --}}
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
        Order #{{ $order->order_number }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
        Placed on <span class="font-medium">{{ $order->created_at->format('M j, Y g:i A') }}</span>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <span class="text-xs px-2 py-1 rounded-full {{ $badge($order->payment_status, [
          'paid'=>'paid','pending'=>'pending','failed'=>'failed','refunded'=>'refunded'
      ]) }}">
        {{ ucfirst($order->payment_status) }}
      </span>
      <span class="text-xs px-2 py-1 rounded-full {{ $badge($order->order_status, [
          'processing'=>'processing','pending'=>'pending','shipped'=>'shipped',
          'delivered'=>'delivered','cancelled'=>'cancelled'
      ]) }}">
        {{ ucfirst($order->order_status) }}
      </span>
    </div>
  </div>

  @if (session('success'))
    <div class="mt-4 rounded-lg bg-green-50 text-green-800 px-4 py-2 text-sm">
      {{ session('success') }}
    </div>
  @endif

  {{-- Top actions --}}
  <div class="mt-6 flex flex-wrap gap-2">
    <a href="{{ url()->previous() }}"
       class="inline-flex items-center px-4 py-2 rounded-lg ring-1 ring-gray-300 text-gray-800 hover:bg-gray-100 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">
      ← Back
    </a>
    <a href="{{ route('admin.orders.index') }}"
       class="inline-flex items-center px-4 py-2 rounded-lg bg-black text-white hover:bg-black/90">
      Orders
    </a>
    <a href="{{ route('admin.orders.edit', $order) }}"
       class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
      Edit
    </a>
    <form action="{{ route('admin.orders.destroy', $order) }}" method="POST"
          onsubmit="return confirm('Delete this order?');" class="inline">
      @csrf @method('DELETE')
      <button class="inline-flex items-center px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
        Delete
      </button>
    </form>
  </div>

  {{-- Summary cards --}}
  <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 p-4">
      <div class="text-xs uppercase text-gray-500">Customer</div>
      <div class="mt-1 text-gray-900 dark:text-gray-100">
        {{ $order->full_name ?? ($order->user->name ?? 'Guest') }}
      </div>
      @if($order->email)
        <div class="text-sm text-gray-600 dark:text-gray-300">{{ $order->email }}</div>
      @endif
      @if($order->phone)
        <div class="text-sm text-gray-600 dark:text-gray-300">{{ $order->phone }}</div>
      @endif
    </div>

    <div class="rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 p-4">
      <div class="text-xs uppercase text-gray-500">Payment</div>
      <div class="mt-1 text-gray-900 dark:text-gray-100">
        Method: {{ ucfirst(str_replace('_',' ',$order->payment_method ?? '—')) }}
      </div>
      @if($order->paid_at)
        <div class="text-sm text-gray-600 dark:text-gray-300">Paid at: {{ $order->paid_at->format('M j, Y g:i A') }}</div>
      @endif
      @if($order->stripe_payment_intent)
        <div class="text-xs text-gray-500">PI: {{ $order->stripe_payment_intent }}</div>
      @endif
    </div>

    <div class="rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 p-4">
      <div class="text-xs uppercase text-gray-500">Totals</div>
      <dl class="mt-2 space-y-1 text-sm">
        <div class="flex justify-between">
          <dt class="text-gray-600 dark:text-gray-300">Subtotal</dt>
          <dd class="text-gray-900 dark:text-gray-100">{{ $currency }} {{ $fmt($order->subtotal_cents) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-600 dark:text-gray-300">Discount</dt>
          <dd class="text-gray-900 dark:text-gray-100">- {{ $currency }} {{ $fmt($order->discount_cents) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-600 dark:text-gray-300">Shipping</dt>
          <dd class="text-gray-900 dark:text-gray-100">{{ $currency }} {{ $fmt($order->shipping_cents) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-600 dark:text-gray-300">Tax</dt>
          <dd class="text-gray-900 dark:text-gray-100">{{ $currency }} {{ $fmt($order->tax_cents) }}</dd>
        </div>
        <div class="border-t border-gray-200 dark:border-gray-700 pt-2 flex justify-between font-semibold">
          <dt>Total</dt>
          <dd>{{ $currency }} {{ $fmt($order->total_cents) }}</dd>
        </div>
      </dl>
    </div>
  </div>

  @php
  // Read promos from order->metadata
  $meta           = (array) ($order->metadata ?? []);
  $promosApplied  = (array) ($meta['promos_applied'] ?? []);

  // Split into shipping + discount (there can be up to 2 according to your rules)
  $shipPromo   = collect($promosApplied)->firstWhere('type', 'shipping') ?? [];
  $discPromo   = collect($promosApplied)->first(function($p){
      return in_array(($p['type'] ?? ''), ['fixed','percentage'], true);
  }) ?? [];

  // Helpers
  $discCode    = $discPromo['code'] ?? null;
  $discType    = $discPromo['type'] ?? null;         // 'fixed' | 'percentage'
  $discPercent = $discPromo['percent'] ?? null;      // if percentage
  $discAmount  = (int) ($discPromo['amount_cents'] ?? 0);
  $shipCode    = $shipPromo['code'] ?? null;

  $fmt = fn($c) => number_format(($c ?? 0)/100, 2);
@endphp

{{-- Promotions (from order->metadata.promos_applied) --}}
<div class="mt-6 bg-white dark:bg-gray-900 rounded-2xl shadow ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Promotions</h3>
  </div>

  <div class="px-5 py-4">
    @if(!$shipCode && !$discCode)
      <p class="text-sm text-gray-600 dark:text-gray-300">No promotions applied.</p>
    @else
      <ul class="space-y-3">
        {{-- Free Shipping --}}
        @if($shipCode)
          <li class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                Free Shipping
              </span>
              <span class="text-sm text-gray-800 dark:text-gray-100">
                Code: <span class="font-mono">{{ strtoupper($shipCode) }}</span>
              </span>
            </div>
            <span class="text-sm font-medium text-emerald-700">Shipping waived</span>
          </li>
        @endif

        {{-- Discount (fixed or percentage) --}}
        @if($discCode)
          <li class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                {{ $discType === 'percentage' ? ($discPercent.'% Off') : 'Amount Off' }}
              </span>
              <span class="text-sm text-gray-800 dark:text-gray-100">
                Code: <span class="font-mono">{{ strtoupper($discCode) }}</span>
              </span>
            </div>
            <span class="text-sm font-medium text-emerald-700">
              {{-- If you captured the actual removed amount in amount_cents, show it --}}
              @if($discAmount > 0)
                − {{ $order->currency ?? 'USD' }} {{ $fmt($discAmount) }}
              @endif
            </span>
          </li>
        @endif
      </ul>
    @endif
  </div>
</div>


  {{-- Addresses --}}
  <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 p-4">
      <div class="text-xs uppercase text-gray-500">Shipping Address</div>
      <pre class="mt-2 whitespace-pre-wrap text-sm text-gray-900 dark:text-gray-100 leading-6">{{ $addr($ship) ?: '—' }}</pre>
    </div>
    <div class="rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 p-4">
      <div class="text-xs uppercase text-gray-500">Billing Address</div>
      <pre class="mt-2 whitespace-pre-wrap text-sm text-gray-900 dark:text-gray-100 leading-6">{{ $addr($bill) ?: '—' }}</pre>
    </div>
  </div>

  {{-- Items --}}
  <div class="mt-8 rounded-2xl overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700">
    <div class="px-5 py-4 bg-gray-50 dark:bg-gray-800">
      <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Items</h3>
    </div>

    <div class="overflow-x-auto bg-white dark:bg-gray-900">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
          <tr>
            <th class="px-5 py-3 text-left font-medium">Product</th>
            <th class="px-5 py-3 text-left font-medium">Variant</th>
            <th class="px-5 py-3 text-right font-medium">Qty</th>
            <th class="px-5 py-3 text-right font-medium">Unit</th>
            <th class="px-5 py-3 text-right font-medium">Subtotal</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          @foreach ($order->items as $item)
            @php
              $snap = (array) ($item->snapshot ?? []);
              $color = $snap['color'] ?? data_get($snap, 'variant.color');
              $size  = $snap['size']  ?? data_get($snap, 'variant.size');
              $thumb = data_get($snap, 'image_url'); // if you saved one
            @endphp
            <tr class="text-gray-900 dark:text-gray-100">
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  @if($thumb)
                    <img src="{{ $thumb }}" class="w-10 h-10 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700" alt="">
                  @endif
                  <div>
                    <div class="font-medium">{{ $item->name ?? ($item->product->name ?? 'Item') }}</div>
                    @if($item->sku)
                      <div class="text-xs text-gray-500">SKU: {{ $item->sku }}</div>
                    @endif
                  </div>
                </div>
              </td>
              <td class="px-5 py-4">
                <div class="text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                  <div>Color: <span class="font-medium">{{ $color ?: '—' }}</span></div>
                  <div>Size:  <span class="font-medium">{{ $size  ?: '—' }}</span></div>
                </div>
              </td>
              <td class="px-5 py-4 text-right">{{ (int) $item->quantity }}</td>
              <td class="px-5 py-4 text-right">{{ $currency }} {{ $fmt($item->unit_price_cents) }}</td>
              <td class="px-5 py-4 text-right">{{ $currency }} {{ $fmt($item->subtotal_cents) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <td colspan="4" class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">Subtotal</td>
            <td class="px-5 py-3 text-right">{{ $currency }} {{ $fmt($order->subtotal_cents) }}</td>
          </tr>
          <tr>
            <td colspan="4" class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">Discount</td>
            <td class="px-5 py-3 text-right">- {{ $currency }} {{ $fmt($order->discount_cents) }}</td>
          </tr>
          <tr>
            <td colspan="4" class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">Shipping</td>
            <td class="px-5 py-3 text-right">{{ $currency }} {{ $fmt($order->shipping_cents) }}</td>
          </tr>
          <tr>
            <td colspan="4" class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">Tax</td>
            <td class="px-5 py-3 text-right">{{ $currency }} {{ $fmt($order->tax_cents) }}</td>
          </tr>
          <tr class="font-semibold">
            <td colspan="4" class="px-5 py-3 text-right">Total</td>
            <td class="px-5 py-3 text-right">{{ $currency }} {{ $fmt($order->total_cents) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  {{-- Notes --}}
  @if($order->notes)
    <div class="mt-6 rounded-2xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 p-4">
      <div class="text-xs uppercase text-gray-500">Notes</div>
      <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $order->notes }}</p>
    </div>
  @endif
</div>
@endsection
