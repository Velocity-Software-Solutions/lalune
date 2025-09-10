@extends('layouts.admin')

@section('title', 'New Order')

@section('content')
@php
  /**
   * Build the list of known customer emails from previous orders.
   * Expect one of these to be available from the controller:
   * - $orders (collection of Order models)
   * - $customers (collection of User models) as fallback
   */
  $knownFromOrders = collect($orders ?? [])
      ->map(fn($o) => [
          'email'   => $o->email,
          'name'    => $o->full_name,
          'user_id' => $o->user_id,   // may be null for guests; that's OK
      ])
      ->filter(fn($x) => !empty($x['email']))
      ->unique('email')
      ->values();

  $knownFromUsers = collect($customers ?? [])
      ->map(fn($u) => [
          'email'   => $u->email,
          'name'    => $u->name,
          'user_id' => $u->id,
      ])
      ->filter(fn($x) => !empty($x['email']))
      ->unique('email')
      ->values();

  // Prefer emails seen on orders; fallback to users if empty
  $knownCustomers = $knownFromOrders->isNotEmpty() ? $knownFromOrders : $knownFromUsers;
@endphp

<div x-data="orderCreate()" class="h-full max-h-full p-5 mx-3 overflow-scroll bg-white rounded-md shadow-md dark:bg-gray-800 scroll scroll-m-0 custom-scroll">
  <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white">Create Order</h2>

  @if ($errors->any())
    <div class="mb-4 p-4 rounded bg-red-100 text-red-700">
      <strong>There were problems with your input:</strong>
      <ul class="list-disc ml-5 mt-2 text-sm">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.orders.store') }}" method="POST" class="space-y-8">
    @csrf

    {{-- ===================== Customer ===================== --}}
    <div x-data="{ mode: '{{ old('customer_mode','existing') }}' }" class="space-y-3">
      <label class="block text-sm font-medium">Customer <span class="text-red-500">*</span></label>

      <div class="flex gap-4 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="radio" name="customer_mode" value="existing" x-model="mode" class="form-radio">
          <span>Use an email from previous orders</span>
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="radio" name="customer_mode" value="new" x-model="mode" class="form-radio">
          <span>Enter a new customer</span>
        </label>
      </div>

      {{-- Existing customer (email seen before) --}}
      <div x-show="mode==='existing'" x-cloak class="space-y-2">
        <select
          class="form-select w-full"
          x-on:change="
            $refs.existing_email_hidden.value  = $event.target.value;
            $refs.existing_user_id_hidden.value = $event.target.selectedOptions[0]?.dataset.userId || '';
          "
        >
          <option value="" disabled selected>Select customer email…</option>
          @foreach($knownCustomers as $c)
            <option
              value="{{ $c['email'] }}"
              data-user-id="{{ $c['user_id'] ?? '' }}"
              @selected(old('email') === ($c['email']))
            >
              {{ $c['email'] }} {{ $c['name'] ? '— '.$c['name'] : '' }}
            </option>
          @endforeach
        </select>

        {{-- Hidden fields normalized for backend --}}
        <input type="hidden" name="email"   x-ref="existing_email_hidden"   value="{{ old('email') }}">
        <input type="hidden" name="user_id" x-ref="existing_user_id_hidden" value="{{ old('user_id') }}">
      </div>

      {{-- New customer --}}
      <div x-show="mode==='new'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Name</label>
          <input type="text" name="name" value="{{ old('name') }}" class="form-input w-full" placeholder="Customer name">
          @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Email <span class="text-red-500">*</span></label>
          <input type="email" name="email" value="{{ old('email') }}" class="form-input w-full" placeholder="customer@example.com">
          @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        {{-- When creating a brand new customer, user_id should be blank --}}
        <input type="hidden" name="user_id" value="">
      </div>
    </div>
    {{-- =================== /Customer ====================== --}}

    {{-- Shipping Address --}}
    <div>
      <label class="block text-sm font-medium mb-1">Shipping Address <span class="text-red-500">*</span></label>
      <textarea name="shipping_address" rows="3" class="form-textarea w-full" required>{{ old('shipping_address') }}</textarea>
      @error('shipping_address')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Billing Address --}}
    <div x-data="{ sameAsShipping: {{ old('billing_address') ? 'false' : 'true' }} }">
      <label class="inline-flex items-center space-x-2 mb-2">
        <input type="checkbox" x-model="sameAsShipping" class="form-checkbox">
        <span class="text-sm">Billing same as shipping</span>
      </label>
      <textarea
        name="billing_address"
        rows="3"
        class="form-textarea w-full"
        x-bind:readonly="sameAsShipping"
        x-bind:class="sameAsShipping ? 'opacity-50' : ''"
      >{{ old('billing_address') }}</textarea>
      @error('billing_address')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Payment Method --}}
    <div>
      <label class="block text-sm font-medium mb-1">Payment Method <span class="text-red-500">*</span></label>
      <input type="text" name="payment_method" value="{{ old('payment_method', 'manual') }}" class="form-input w-full" required>
      @error('payment_method')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Coupon --}}
    <div>
      <label class="block text-sm font-medium mb-1">Coupon <span class="text-red-500">*</span></label>
      <select name="coupon_id" class="form-select w-full" required>
        @foreach($coupons as $cp)
          <option value="{{ $cp->id }}" @selected(old('coupon_id', $noCouponId) == $cp->id)>{{ $cp->code }}</option>
        @endforeach
      </select>
      @error('coupon_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Notes --}}
    <div>
      <label class="block text-sm font-medium mb-1">Notes</label>
      <textarea name="notes" rows="2" class="form-textarea w-full">{{ old('notes') }}</textarea>
      @error('notes')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Items Table (unchanged from your version) --}}
    {{-- ... keep your existing items table & totals code here ... --}}

    <div class="flex justify-end space-x-2 pt-4">
      <a href="{{ route('admin.orders.index') }}" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-white rounded">Cancel</a>
      <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Create Order</button>
    </div>
  </form>
</div>

<script>
function orderCreate() {
  return {
    // your existing items logic…
    sameAsShipping: true,
    rows: [newRow()],
    itemsTotal: 0,
    shippingTotal: 0,
    grandTotal: 0,
    addRow() { this.rows.push(newRow()); this.recalc(); },
    removeRow(i) { this.rows.splice(i,1); this.recalc(); },
    onProductChange(i) {
      const sel = event.target;
      const price = parseFloat(sel.selectedOptions[0]?.dataset.price || 0);
      this.rows[i].price = price;
      if (!this.rows[i].product_name) {
        this.rows[i].product_name = sel.selectedOptions[0]?.textContent.trim();
      }
      this.recalc();
    },
    recalc() {
      this.itemsTotal = this.rows.reduce((sum,r)=>sum + (r.price * r.quantity), 0);
      const shipSel = document.querySelector('select[name=shipping_option_id]');
      const shipPrice = parseFloat(shipSel?.selectedOptions[0]?.dataset.price || 0);
      this.shippingTotal = shipPrice;
      this.grandTotal = this.itemsTotal + this.shippingTotal; // simple client-side preview
    },
  }
}
function newRow(){return{uid:crypto.randomUUID(),product_id:'',product_name:'',price:0,quantity:1}};
</script>
@endsection
