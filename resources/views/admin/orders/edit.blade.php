@extends('layouts.admin')

@section('title', 'Edit Order ' . $order->order_number)

@push('head')
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
@php
  $ship = (array) ($order->shipping_address_json ?? []);
  $bill = (array) ($order->billing_address_json ?? []);
  $currency = $order->currency ?? 'USD';

  // Helpers for safe display
  $money = fn($cents) => number_format(($cents ?? 0) / 100, 2);
  $get  = fn($a, $key, $def='') => old($key, data_get($a, $key, $def));
@endphp

<div
  x-data="orderEditor({
      currency: @js($currency),
      shipping: @js($ship),
      billing:  @js($bill),
      items: @js(
        $order->items->map(fn($it) => [
          'id'       => $it->id,
          'name'     => $it->name,
          'sku'      => $it->sku,
          'quantity' => (int) $it->quantity,
          'unit'     => (int) $it->unit_price_cents,   // cents
          'subtotal' => (int) $it->subtotal_cents,     // cents
          'total'    => (int) $it->total_cents,        // cents
          'snapshot' => $it->snapshot ?? [],
        ])->values()
      ),
      currentTotals: {
        subtotal: {{ (int) $order->subtotal_cents }},
        discount: {{ (int) $order->discount_cents }},
        shipping: {{ (int) $order->shipping_cents }},
        tax:      {{ (int) $order->tax_cents }},
        total:    {{ (int) $order->total_cents }},
      }
  })"
  class="h-full max-h-full p-5 mx-3 overflow-auto bg-white rounded-md shadow-md dark:bg-gray-800 custom-scroll"
>
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-white">
      Edit Order #{{ $order->order_number }}
    </h2>

    <div class="flex gap-2">
      <a href="{{ route('admin.orders.index') }}"
         class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg shadow-md hover:bg-blue-700">
        Orders
      </a>
    </div>
  </div>

  @if ($errors->any())
    <div class="p-4 mb-6 text-red-700 bg-red-100 rounded">
      <ul class="ml-5 text-sm list-disc">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.orders.update', $order) }}" method="POST" class="space-y-8">
    @csrf
    @method('PUT')

    {{-- Status Row --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white dark:bg-gray-900 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-gray-700">
        <label class="block mb-1 text-sm font-medium">Order Status <span class="text-red-500">*</span></label>
        <select name="order_status" class="w-full form-select" required>
          @foreach (['pending','processing','shipped','delivered','cancelled'] as $status)
            <option value="{{ $status }}" @selected(old('order_status', $order->order_status) === $status)>
              {{ ucfirst($status) }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="bg-white dark:bg-gray-900 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-gray-700">
        <label class="block mb-1 text-sm font-medium">Payment Status <span class="text-red-500">*</span></label>
        <select name="payment_status" class="w-full form-select" required>
          @foreach (['pending','paid','failed','refunded'] as $pay)
            <option value="{{ $pay }}" @selected(old('payment_status', $order->payment_status) === $pay)>
              {{ ucfirst($pay) }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="bg-white dark:bg-gray-900 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-gray-700">
        <label class="block mb-1 text-sm font-medium">Notes</label>
        <textarea name="notes" rows="2" class="w-full form-textarea">{{ old('notes', $order->notes) }}</textarea>
      </div>
    </div>

    {{-- Addresses --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {{-- Shipping --}}
      <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 ring-1 ring-gray-200 dark:ring-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Shipping Address</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="text-sm">Name</label>
            <input name="ship[name]" type="text" class="form-input w-full"
                   value="{{ old('ship.name', $ship['name'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Email</label>
            <input name="ship[email]" type="email" class="form-input w-full"
                   value="{{ old('ship.email', $ship['email'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Phone</label>
            <input name="ship[phone]" type="text" class="form-input w-full"
                   value="{{ old('ship.phone', $ship['phone'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Country</label>
            <input name="ship[country]" type="text" class="form-input w-full"
                   value="{{ old('ship.country', $ship['country'] ?? '') }}">
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm">Address Line 1</label>
            <input name="ship[line1]" type="text" class="form-input w-full"
                   value="{{ old('ship.line1', $ship['line1'] ?? '') }}">
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm">Address Line 2</label>
            <input name="ship[line2]" type="text" class="form-input w-full"
                   value="{{ old('ship.line2', $ship['line2'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">City</label>
            <input name="ship[city]" type="text" class="form-input w-full"
                   value="{{ old('ship.city', $ship['city'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">State / Province</label>
            <input name="ship[state]" type="text" class="form-input w-full"
                   value="{{ old('ship.state', $ship['state'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Postal Code</label>
            <input name="ship[postal_code]" type="text" class="form-input w-full"
                   value="{{ old('ship.postal_code', $ship['postal_code'] ?? '') }}">
          </div>
        </div>
      </div>

      {{-- Billing --}}
      <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 ring-1 ring-gray-200 dark:ring-gray-700">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Billing Address</h3>
          <button type="button"
                  class="text-sm px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
                  @click="copyShipToBill()">Copy shipping</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="text-sm">Name</label>
            <input name="bill[name]" type="text" class="form-input w-full"
                   value="{{ old('bill.name', $bill['name'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Email</label>
            <input name="bill[email]" type="email" class="form-input w-full"
                   value="{{ old('bill.email', $bill['email'] ?? '') }}">
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm">Address Line 1</label>
            <input name="bill[line1]" type="text" class="form-input w-full"
                   value="{{ old('bill.line1', $bill['line1'] ?? '') }}">
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm">Address Line 2</label>
            <input name="bill[line2]" type="text" class="form-input w-full"
                   value="{{ old('bill.line2', $bill['line2'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">City</label>
            <input name="bill[city]" type="text" class="form-input w-full"
                   value="{{ old('bill.city', $bill['city'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">State / Province</label>
            <input name="bill[state]" type="text" class="form-input w-full"
                   value="{{ old('bill.state', $bill['state'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Postal Code</label>
            <input name="bill[postal_code]" type="text" class="form-input w-full"
                   value="{{ old('bill.postal_code', $bill['postal_code'] ?? '') }}">
          </div>
          <div>
            <label class="text-sm">Country</label>
            <input name="bill[country]" type="text" class="form-input w-full"
                   value="{{ old('bill.country', $bill['country'] ?? '') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Items --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ordered Items</h3>
      </div>

      <div class="overflow-x-auto" x-init="$nextTick(()=>recalcAll())">
        <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
            <tr>
              <th class="px-5 py-3 text-left font-medium">Product</th>
              <th class="px-5 py-3 text-left font-medium">Variant</th>
              <th class="px-5 py-3 text-right font-medium">Qty</th>
              <th class="px-5 py-3 text-right font-medium">Unit ({{ $currency }})</th>
              <th class="px-5 py-3 text-right font-medium">Subtotal</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
            @foreach ($order->items as $i => $item)
              @php
                $snap = (array) ($item->snapshot ?? []);
                $color = $snap['color'] ?? data_get($snap, 'variant.color');
                $size  = $snap['size']  ?? data_get($snap, 'variant.size');
              @endphp
              <tr class="text-gray-900 dark:text-gray-100">
                <td class="px-5 py-4">
                  <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                  <div class="font-medium">{{ $item->name }}</div>
                  @if($item->sku)
                    <div class="text-xs text-gray-500">SKU: {{ $item->sku }}</div>
                  @endif>
                </td>

                <td class="px-5 py-4">
                  <div class="text-xs text-gray-700 dark:text-gray-300">
                    <div>Color:
                      <input type="text" name="items[{{ $i }}][color]"
                             value="{{ old("items.$i.color", $color) }}"
                             class="form-input form-input-sm w-32 inline-block ml-1">
                    </div>
                    <div class="mt-1">Size:
                      <input type="text" name="items[{{ $i }}][size]"
                             value="{{ old("items.$i.size", $size) }}"
                             class="form-input form-input-sm w-24 inline-block ml-1">
                    </div>
                  </div>
                </td>

                <td class="px-5 py-4 text-right">
                  <input type="number" min="1"
                         x-model.number="items[{{ $i }}].quantity"
                         @input="recalcRow({{ $i }})"
                         name="items[{{ $i }}][quantity]"
                         value="{{ old("items.$i.quantity", $item->quantity) }}"
                         class="w-20 text-right form-input">
                </td>

                <td class="px-5 py-4 text-right">
                  <input type="number" step="0.01" min="0"
                         x-model.number="items[{{ $i }}].unit_dollars"
                         @input="recalcRow({{ $i }})"
                         name="items[{{ $i }}][unit_price]"
                         value="{{ old("items.$i.unit_price", $money($item->unit_price_cents)) }}"
                         class="w-28 text-right form-input">
                </td>

                <td class="px-5 py-4 text-right">
                  <span x-text="fmtMoney(items[{{ $i }}].subtotal)"></span>
                </td>

                <td class="px-5 py-4 text-right text-sm text-gray-500">
                  {{-- keep for future remove/add UI --}}
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot class="bg-gray-50 dark:bg-gray-800 text-sm">
            <tr>
              <td colspan="4" class="px-5 py-3 text-right text-gray-600">Subtotal</td>
              <td class="px-5 py-3 text-right">
                <span x-text="fmtMoney(totals.subtotal)"></span>
              </td>
              <td></td>
            </tr>
            <tr>
              <td colspan="4" class="px-5 py-3 text-right text-gray-600">Discount</td>
              <td class="px-5 py-3 text-right">
                <input type="number" step="0.01" min="0"
                       x-model.number="totals.discount_dollars"
                       @input="recalcTotals()"
                       name="discount_amount"
                       value="{{ old('discount_amount', $money($order->discount_cents)) }}"
                       class="w-28 text-right form-input">
              </td>
              <td></td>
            </tr>
            <tr>
              <td colspan="4" class="px-5 py-3 text-right text-gray-600">Shipping</td>
              <td class="px-5 py-3 text-right">
                <input type="number" step="0.01" min="0"
                       x-model.number="totals.shipping_dollars"
                       @input="recalcTotals()"
                       name="shipping_amount"
                       value="{{ old('shipping_amount', $money($order->shipping_cents)) }}"
                       class="w-28 text-right form-input">
              </td>
              <td></td>
            </tr>
            <tr>
              <td colspan="4" class="px-5 py-3 text-right text-gray-600">Tax</td>
              <td class="px-5 py-3 text-right">
                <input type="number" step="0.01" min="0"
                       x-model.number="totals.tax_dollars"
                       @input="recalcTotals()"
                       name="tax_amount"
                       value="{{ old('tax_amount', $money($order->tax_cents)) }}"
                       class="w-28 text-right form-input">
              </td>
              <td></td>
            </tr>
            <tr class="font-semibold">
              <td colspan="4" class="px-5 py-3 text-right">Total ({{ $currency }})</td>
              <td class="px-5 py-3 text-right">
                <span x-text="fmtMoney(totals.total)"></span>
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <a href="{{ route('admin.orders.show', $order) }}"
         class="px-4 py-2 text-gray-800 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-white">Cancel</a>
      <button type="submit"
              class="px-4 py-2 text-white bg-green-600 rounded hover:bg-green-700">
        Save Changes
      </button>
    </div>
  </form>
</div>

<script>
function orderEditor({currency, shipping, billing, items, currentTotals}){
  // Convert item cents into dollar fields for editing
  items = items.map(it => ({
    ...it,
    unit_dollars: (it.unit ?? 0) / 100,
    subtotal: it.quantity * (it.unit ?? 0) // cents
  }));

  return {
    currency,
    items,
    ship: {...shipping},
    bill: {...billing},
    totals: {
      ...currentTotals,
      discount_dollars: (currentTotals.discount ?? 0) / 100,
      shipping_dollars: (currentTotals.shipping ?? 0) / 100,
      tax_dollars:      (currentTotals.tax ?? 0) / 100,
    },

    fmtMoney(cents){ return (cents/100).toFixed(2); },

    copyShipToBill(){
      this.bill = {
        name: this.ship.name || '',
        email: this.ship.email || '',
        line1: this.ship.line1 || '',
        line2: this.ship.line2 || '',
        city: this.ship.city || '',
        state: this.ship.state || '',
        postal_code: this.ship.postal_code || '',
        country: this.ship.country || ''
      };
      // also write into inputs
      for (const k in this.bill) {
        const el = document.querySelector(`[name="bill[${k}]"]`);
        if (el) el.value = this.bill[k] ?? '';
      }
    },

    recalcRow(i){
      const it = this.items[i];
      const unitCents = Math.round((it.unit_dollars ?? 0) * 100);
      const qty = Math.max(1, parseInt(it.quantity || 1, 10));
      it.subtotal = unitCents * qty;
      this.recalcTotals();
    },

    recalcAll(){
      this.items.forEach((_, i) => this.recalcRow(i));
    },

    recalcTotals(){
      const subtotal = this.items.reduce((sum, it) => sum + (it.subtotal ?? 0), 0);
      const discount = Math.round((this.totals.discount_dollars ?? 0) * 100);
      const shipping = Math.round((this.totals.shipping_dollars ?? 0) * 100);
      const tax      = Math.round((this.totals.tax_dollars ?? 0) * 100);
      const total    = Math.max(0, subtotal - discount + shipping + tax);
      this.totals = { ...this.totals, subtotal, discount, shipping, tax, total,
        discount_dollars: discount/100, shipping_dollars: shipping/100, tax_dollars: tax/100
      };
    },
  }
}
</script>
@endsection
