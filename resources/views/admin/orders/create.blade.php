@extends('layouts.admin')

@section('title', 'New Order')

@section('content')
@php
    // Provide an ID for a no-discount fallback coupon if required by schema.
    $noCouponId = $coupons->firstWhere('code', 'NOCOUPON')->id ?? ($coupons->first()->id ?? null);
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

        {{-- Customer --}}
        <div>
            <label class="block text-sm font-medium mb-1">Customer <span class="text-red-500">*</span></label>
            <select name="user_id" class="form-select w-full" required>
                <option value="" disabled selected>Select Customer...</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}" @selected(old('user_id') == $c->id)>{{ $c->name }} ({{ $c->email }})</option>
                @endforeach
            </select>
            @error('user_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Shipping Address --}}
        <div>
            <label class="block text-sm font-medium mb-1">Shipping Address <span class="text-red-500">*</span></label>
            <textarea name="shipping_address" rows="3" class="form-textarea w-full" required>{{ old('shipping_address') }}</textarea>
            @error('shipping_address')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Billing Address (optional) --}}
        <div>
            <label class="inline-flex items-center space-x-2 mb-2">
                <input type="checkbox" x-model="sameAsShipping" class="form-checkbox">
                <span class="text-sm">Billing same as shipping</span>
            </label>
            <textarea name="billing_address" rows="3" class="form-textarea w-full" x-bind:readonly="sameAsShipping" x-bind:class="sameAsShipping ? 'opacity-50' : ''">{{ old('billing_address') }}</textarea>
            @error('billing_address')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Payment Method --}}
        <div>
            <label class="block text-sm font-medium mb-1">Payment Method <span class="text-red-500">*</span></label>
            <input type="text" name="payment_method" value="{{ old('payment_method', 'manual') }}" class="form-input w-full" required>
            @error('payment_method')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Coupon (required per schema) --}}
        <div>
            <label class="block text-sm font-medium mb-1">Coupon <span class="text-red-500">*</span></label>
            <select name="coupon_id" class="form-select w-full" required>
                @foreach($coupons as $cp)
                    <option value="{{ $cp->id }}" @selected(old('coupon_id', $noCouponId) == $cp->id)>{{ $cp->code }}</option>
                @endforeach
            </select>
            @error('coupon_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Shipping Option (required) --}}
        <div>
            <label class="block text-sm font-medium mb-1">Shipping Option <span class="text-red-500">*</span></label>
            <select name="shipping_option_id" class="form-select w-full" required @change="recalc">
                <option value="" disabled selected>Select Shipping...</option>
                @foreach($shippingOptions as $opt)
                    <option value="{{ $opt->id }}" data-price="{{ $opt->price }}" @selected(old('shipping_option_id') == $opt->id)>
                        {{ $opt->name }} ({{ $opt->delivery_time }}) - ${{ number_format($opt->price,2) }}
                    </option>
                @endforeach
            </select>
            @error('shipping_option_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" rows="2" class="form-textarea w-full">{{ old('notes') }}</textarea>
            @error('notes')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Items Table --}}
        <div>
            <h3 class="text-lg font-semibold mb-2">Items</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-sm border border-gray-200 dark:border-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white">
                    <tr>
                        <th class="p-2 text-left">Product</th>
                        <th class="p-2 text-right">Qty</th>
                        <th class="p-2 text-right">Price</th>
                        <th class="p-2 text-right">Subtotal</th>
                        <th class="p-2 text-center">Remove</th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(row, idx) in rows" :key="row.uid">
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            {{-- Hidden row index mapping for PHP arrays --}}
                            <td class="p-2">
                                <div class="space-y-1">
                                    <select x-model="row.product_id" @change="onProductChange(idx)" :name="`items[${idx}][product_id]`" class="form-select w-full">
                                        <option value="">-- Custom Item --</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}" data-price="{{ $p->price }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" x-model="row.product_name" :name="`items[${idx}][product_name]`" placeholder="Item name" class="form-input w-full text-xs" />
                                </div>
                            </td>
                            <td class="p-2 text-right">
                                <input type="number" min="1" x-model.number="row.quantity" @input="recalc" :name="`items[${idx}][quantity]`" class="form-input w-20 text-right text-xs" />
                            </td>
                            <td class="p-2 text-right">
                                <input type="number" step="0.01" min="0" x-model.number="row.price" @input="recalc" :name="`items[${idx}][price]`" class="form-input w-24 text-right text-xs" />
                            </td>
                            <td class="p-2 text-right">$<span x-text="(row.price * row.quantity).toFixed(2)"></span></td>
                            <td class="p-2 text-center">
                                <button type="button" @click="removeRow(idx)" class="text-red-600 hover:underline">X</button>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="5" class="p-2 text-right">
                            <button type="button" @click="addRow()" class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs">+ Add Item</button>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Totals Summary (client side only; backend authoritative) --}}
        <div class="text-right text-lg font-semibold">
            Items Total: $<span x-text="itemsTotal.toFixed(2)"></span><br>
            Shipping: $<span x-text="shippingTotal.toFixed(2)"></span><br>
            <span class="text-xl">Grand Total: $<span x-text="grandTotal.toFixed(2)"></span></span>
        </div>

        <div class="flex justify-end space-x-2 pt-4">
            <a href="{{ route('admin.orders.index') }}" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-white rounded">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Create Order</button>
        </div>
    </form>
</div>

<script>
function orderCreate() {
    return {
        sameAsShipping: true,
        rows: [newRow()],
        itemsTotal: 0,
        shippingTotal: 0,
        grandTotal: 0,
        addRow() { this.rows.push(newRow()); this.recalc(); },
        removeRow(i) { this.rows.splice(i,1); this.recalc(); },
        onProductChange(i) {
            // Pull price from <option data-price="...">
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
            // shipping from select element name=shipping_option_id
            const shipSel = document.querySelector('select[name=shipping_option_id]');
            const shipPrice = parseFloat(shipSel?.selectedOptions[0]?.dataset.price || 0);
            this.shippingTotal = shipPrice;
            this.grandTotal = this.itemsTotal + this.shippingTotal;
        }
    }
}
function newRow(){return{uid:crypto.randomUUID(),product_id:'',product_name:'',price:0,quantity:1}};
</script>
@endsection