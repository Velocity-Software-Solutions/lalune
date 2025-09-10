@extends('layouts.admin')

@section('title', 'Manage Promo Codes')

@section('content')
<div class="max-w-6xl p-6 mx-2 h-full space-y-6 bg-white rounded-md shadow-md dark:bg-gray-800"
     x-data="{ showNewRow: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Promo Codes</h2>
        <button @click="showNewRow = true" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
            + Add Promo Code
        </button>
    </div>

    {{-- Flash --}}
    @if (session('success'))
      <div class="px-4 py-2 text-green-700 bg-green-100 rounded">
        {{ session('success') }}
      </div>
    @endif
    @if ($errors->any())
      <div class="px-4 py-2 text-red-700 bg-red-100 rounded">
        <ul class="list-disc ml-5">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto border border-gray-200 rounded-md dark:border-gray-700">
        <table class="min-w-full table-auto border-collapse rounded overflow-hidden shadow-sm bg-white dark:bg-gray-900">
            <thead class="text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-white">
            <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Code</th>
                <th class="px-4 py-2">Type</th>
                <th class="px-4 py-2">Value</th>
                <th class="px-4 py-2">Min Order</th>
                <th class="px-4 py-2">Limit</th>
                <th class="px-4 py-2">Used</th>
                <th class="px-4 py-2">Remaining</th>
                <th class="px-4 py-2">Expires</th>
                <th class="px-4 py-2">Active</th>
                <th class="px-4 py-2 text-center">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">

            {{-- Create Row --}}
            <tr x-show="showNewRow" x-data="{ type: 'fixed', limit: null }" class="bg-gray-50 dark:bg-gray-900">
                <form action="{{ route('admin.promo-codes.store') }}" method="POST" class="contents">
                    @csrf
                    <td class="px-4 py-2">New</td>

                    <td class="px-4 py-2">
                        <input name="code" class="w-28 text-sm rounded-md form-input" required />
                    </td>

                    <td class="px-4 py-2">
                        <select name="discount_type" x-model="type" class="w-auto text-sm rounded-md form-select" required>
                            <option value="shipping">Free Shipping</option>
                            <option value="fixed">Fixed</option>
                            <option value="percentage">Percentage</option>
                        </select>
                    </td>

                    <td class="px-4 py-2">
                        <div class="flex items-center gap-1">
                            <input name="value" type="number" step="0.01"
                                   x-bind:disabled="type === 'shipping'"
                                   x-bind:placeholder="type === 'percentage' ? 'e.g. 10 (means 10%)' : 'e.g. 10.00'"
                                   class="w-24 text-sm rounded-md form-input" />
                            <span class="text-xs text-gray-500" x-show="type === 'percentage'">% off</span>
                            <span class="text-xs text-gray-500" x-show="type === 'fixed'">CAD</span>
                            <span class="text-xs text-gray-400" x-show="type === 'shipping'">—</span>
                        </div>
                    </td>

                    <td class="px-4 py-2">
                        <input name="min_order_amount" type="number" step="0.01"
                               class="w-24 text-sm rounded-md form-input" />
                    </td>

                    <td class="px-4 py-2">
                        <input name="usage_limit" type="number" min="1"
                               x-model.number="limit"
                               class="w-20 text-sm rounded-md form-input" />
                    </td>

                    <td class="px-4 py-2">
                        <span class="text-sm text-gray-600">0</span>
                    </td>

                    <td class="px-4 py-2">
                        <span class="text-sm text-gray-600" x-text="limit ? Math.max(0, parseInt(limit,10)) : '—'"></span>
                    </td>

                    <td class="px-4 py-2">
                        <input name="expires_at" type="datetime-local"
                               class="text-sm rounded-md form-input w-48" />
                    </td>

                    <td class="px-4 py-2">
                        <select name="is_active" class="w-auto text-sm rounded-md form-select">
                            <option value="1" selected>Yes</option>
                            <option value="0">No</option>
                        </select>
                    </td>

                    <td class="px-4 py-2 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button type="submit"
                                    class="px-2 py-1 text-xs font-semibold text-white bg-green-600 rounded hover:bg-green-700">
                                Save
                            </button>
                            <button type="button" @click="showNewRow=false"
                                    class="px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                                Cancel
                            </button>
                        </div>
                    </td>
                </form>
            </tr>

            {{-- Existing Promo Codes --}}
            @foreach ($promoCodes as $pc)
                @php
                    $used = (int) ($pc->used_count ?? 0);
                    $limit = (int) ($pc->usage_limit ?? 0);
                    $remaining = $limit > 0 ? max(0, $limit - $used) : null;
                @endphp
                <tr x-data="{ type: '{{ $pc->discount_type }}' }">
                    <td class="px-4 py-2">{{ $loop->iteration + ($promoCodes->currentPage() - 1) * $promoCodes->perPage() }}</td>

                    <form action="{{ route('admin.promo-codes.update', $pc) }}" method="POST" class="contents">
                        @csrf
                        @method('PUT')

                        <td class="px-4 py-2">
                            <input name="code" value="{{ $pc->code }}" class="w-28 text-sm rounded-md form-input" required />
                        </td>

                        <td class="px-4 py-2">
                            <select name="discount_type" x-model="type" class="w-auto text-sm rounded-md form-select" required>
                                <option value="shipping">Free Shipping</option>
                                <option value="fixed">Fixed</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </td>

                        <td class="px-4 py-2">
                            <div class="flex items-center gap-1">
                                <input name="value" type="number" step="0.01"
                                       x-bind:disabled="type === 'shipping'"
                                       value="{{ $pc->value }}"
                                       class="w-24 text-sm rounded-md form-input" />
                                <span class="text-xs text-gray-500" x-show="type === 'percentage'">% off</span>
                                <span class="text-xs text-gray-500" x-show="type === 'fixed'">CAD</span>
                                <span class="text-xs text-gray-400" x-show="type === 'shipping'">—</span>
                            </div>
                        </td>

                        <td class="px-4 py-2">
                            <input name="min_order_amount" type="number" step="0.01"
                                   value="{{ $pc->min_order_amount }}"
                                   class="w-24 text-sm rounded-md form-input" />
                        </td>

                        <td class="px-4 py-2">
                            <input name="usage_limit" type="number" min="1"
                                   value="{{ $pc->usage_limit }}"
                                   class="w-20 text-sm rounded-md form-input" />
                        </td>

                        <td class="px-4 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                                         {{ $used > 0 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ $used }}
                            </span>
                        </td>

                        <td class="px-4 py-2">
                            @if(!is_null($remaining))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                                             {{ $remaining > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                    {{ $remaining }}
                                </span>
                            @else
                                <span class="text-xs text-gray-500">∞</span>
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            <input name="expires_at" type="datetime-local"
                                   value="{{ $pc->expires_at ? \Carbon\Carbon::parse($pc->expires_at)->format('Y-m-d\TH:i') : '' }}"
                                   class="text-sm rounded-md form-input w-48" />
                        </td>

                        <td class="px-4 py-2">
                            <select name="is_active" class="w-auto text-sm rounded-md form-select">
                                <option value="1" @selected($pc->is_active)>Yes</option>
                                <option value="0" @selected(!$pc->is_active)>No</option>
                            </select>
                        </td>

                        <td class="px-4 py-2">
                            <div class="flex items-center justify-center gap-2">
                                <button type="submit"
                                        class="px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                                    Update
                                </button>
                            </div>
                        </td>
                    </form>

                    {{-- Delete (separate form, same Actions cell via absolute placement not needed; keep inline) --}}
                    <td class="hidden"></td> {{-- keep table structure consistent --}}
                    <td class="hidden"></td>
                    <td class="hidden"></td>
                    <td class="hidden"></td>
                    <td class="hidden"></td>
                    <td class="hidden"></td>
                    <td class="hidden"></td>
                    <td class="hidden"></td>
                    <td class="px-4 py-2 text-center">
                        <form action="{{ route('admin.promo-codes.destroy', $pc) }}" method="POST"
                              onsubmit="return confirm('Delete this promo code?');" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach

            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex justify-end pt-4">
        {{ $promoCodes->links('pagination::tailwind') }}
    </div>
</div>
@endsection
