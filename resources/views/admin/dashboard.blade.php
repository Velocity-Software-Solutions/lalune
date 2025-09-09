@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
<div class="px-4 py-6 space-y-6 overflow-scroll custom-scrollbar scrollbar-hide">

  {{-- ===== KPIs ===== --}}
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Total sales (30d)</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">
        {{ number_format($kpis['sales_30d'] ?? 0, 2) }}
      </p>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Orders today</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">
        {{ $kpis['orders_today'] ?? 0 }}
      </p>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Pending orders</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">
        {{ $kpis['pending_count'] ?? 0 }}
      </p>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Refunds (30d)</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">
        {{ $kpis['refunds_30d'] ?? 0 }}
      </p>
    </div>
  </div>

  {{-- ===== Orders last 30 days (Chart) ===== --}}
  <div class="bg-white border border-gray-200 rounded-lg p-4">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-900">Orders last 30 days</h3>
      <div class="hidden sm:flex gap-2">
        <button class="px-2 py-1 text-sm bg-gray-100 rounded">Day</button>
        <button class="px-2 py-1 text-sm text-gray-500 hover:text-gray-700">Week</button>
        <button class="px-2 py-1 text-sm text-gray-500 hover:text-gray-700">Month</button>
      </div>
    </div>
    <div class="mt-4 h-56 sm:h-64">
      <canvas id="ordersChart"></canvas>
    </div>
  </div>

  {{-- ===== Pending Orders + Insights ===== --}}
  <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Table --}}
    <div
      x-data="pendingOrdersTable({ orders: @js($pendingOrders ?? []) })"
      x-init="init()"
      class="bg-white border border-gray-200 rounded-lg lg:col-span-2"
    >
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4">
        <h3 class="text-base font-semibold text-gray-900">Pending Orders</h3>
        <div class="flex items-center gap-2 w-full sm:w-auto">
          <input x-model.debounce.300ms="q"
                 type="text"
                 placeholder="Search orders, names, emails…"
                 class="w-full sm:w-72 px-3 py-2 text-sm bg-white border border-gray-300 rounded
                        focus:outline-none focus:ring-2 focus:ring-gray-200 focus:border-gray-400">
          <select x-model="perPage"
                  class="px-2 py-2 text-sm bg-white border border-gray-300 rounded">
            <option value="5">5 / page</option>
            <option value="10">10 / page</option>
            <option value="20">20 / page</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 border-y border-gray-200">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Order #</th>
              <th class="px-4 py-3 text-left font-medium">Customer</th>
              <th class="px-4 py-3 text-left font-medium">Items</th>
              <th class="px-4 py-3 text-right font-medium">Total</th>
              <th class="px-4 py-3 text-left font-medium">Payment</th>
              <th class="px-4 py-3 text-left font-medium">Placed</th>
              <th class="px-4 py-3 text-left font-medium">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-800">
            <template x-for="o in paginated" :key="o.id">
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">
                  <a href="#" class="underline decoration-gray-300 hover:decoration-gray-500" x-text="o.order_number"></a>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-col">
                    <span x-text="o.customer_name || '—'"></span>
                    <span class="text-gray-500 text-xs" x-text="o.customer_email || '—'"></span>
                  </div>
                </td>
                <td class="px-4 py-3" x-text="o.items_count"></td>
                <td class="px-4 py-3 text-right" x-text="'AED ' + Number(o.total||0).toFixed(2)"></td>
                <td class="px-4 py-3" x-text="o.payment_method || '—'"></td>
                <td class="px-4 py-3" x-text="o.placed"></td>
                <td class="px-4 py-3">
                  <a href="#"
                     class="inline-flex items-center px-3 py-1.5 bg-gray-900 text-white rounded hover:bg-gray-800">
                    View
                  </a>
                </td>
              </tr>
            </template>

            <tr x-show="paginated.length === 0">
              <td class="px-4 py-6 text-center text-gray-500" colspan="7">No results</td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      <div class="flex items-center justify-between px-4 py-3">
        <p class="text-xs text-gray-500">
          <span x-text="filtered.length ? startIndex + 1 : 0"></span>–
          <span x-text="endIndex"></span>
          of <span x-text="filtered.length"></span>
        </p>
        <div class="flex items-center gap-1">
          <button @click="prev()" :disabled="page===1"
                  class="px-3 py-1.5 text-sm bg-gray-100 rounded disabled:opacity-50">Previous</button>
          <template x-for="n in totalPages" :key="n">
            <button @click="go(n)"
                    class="px-3 py-1.5 text-sm rounded"
                    :class="page===n ? 'bg-gray-900 text-white' : 'bg-gray-100 hover:bg-gray-200' "
                    x-text="n"></button>
          </template>
          <button @click="next()" :disabled="page===totalPages"
                  class="px-3 py-1.5 text-sm bg-gray-100 rounded disabled:opacity-50">Next</button>
        </div>
      </div>
    </div>

    {{-- Insights --}}
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <h3 class="text-base font-semibold text-gray-900">Insights</h3>
      <ul class="mt-3 space-y-3">
        <li class="flex items-start justify-between">
          <div>
            <p class="font-medium text-gray-800">Low stock</p>
            <p class="text-gray-500 text-sm">
              {{ implode(', ', $insights['low_stock'] ?? []) ?: '—' }}
            </p>
          </div>
          <span class="text-xs px-2 py-1 bg-gray-100 rounded">
            {{ $insights['low_stock_count'] ?? 0 }} SKUs
          </span>
        </li>

        <li class="flex items-start justify-between">
          <div>
            <p class="font-medium text-gray-800">New customers</p>
            <p class="text-gray-500 text-sm">Last 7 days</p>
          </div>
          <span class="text-xs px-2 py-1 bg-gray-100 rounded">
            {{ $insights['new_customers_7d'] ?? 0 }}
          </span>
        </li>

        {{-- Abandoned from browser storage (no server table) --}}
        {{-- <li class="flex items-start justify-between"
            x-data="abandonedWidget({ serverValue: @js($insights['abandoned_checkouts_7d'] ?? null) })"
            x-init="init()">
          <div>
            <p class="font-medium text-gray-800">Abandoned checkouts</p>
            <p class="text-gray-500 text-sm">Last 7 days</p>
          </div>
          <span class="text-xs px-2 py-1 bg-gray-100 rounded" x-text="count ?? '—'"></span>
        </li> --}}
      </ul>
    </div>
  </div>
</div>

{{-- AlpineJS --}}
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Chart setup using real series from controller
  const ctx = document.getElementById('ordersChart');
  const series = @json($ordersSeries ?? ['labels'=>[], 'data'=>[]]);

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: series.labels || [],
      datasets: [{
        label: 'Orders',
        data: series.data || [],
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        borderColor: 'rgba(17,24,39,0.9)',
        backgroundColor: 'rgba(17,24,39,0.08)',
        pointRadius: 2,
        pointHoverRadius: 4,
        pointStyle: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { grid: { display: false }, ticks: { color: '#6b7280' } },
        y: { grid: { color: 'rgba(209,213,219,0.5)' }, ticks: { color: '#6b7280' } }
      },
      plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }
    }
  });
});

// Alpine: Pending orders table
function pendingOrdersTable(initial = { orders: [] }) {
  return {
    orders: initial.orders,
    q: '',
    page: 1,
    perPage: 5,
    init(){ this.compute(); },
    get filtered() {
      if (!this.q) return this.orders;
      const q = this.q.toLowerCase();
      return this.orders.filter(o =>
        String(o.order_number||'').toLowerCase().includes(q) ||
        String(o.customer_name||'').toLowerCase().includes(q) ||
        String(o.customer_email||'').toLowerCase().includes(q)
      );
    },
    get totalPages(){ return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
    get startIndex(){ return (this.page - 1) * this.perPage; },
    get endIndex(){ return Math.min(this.filtered.length, this.page * this.perPage); },
    get paginated(){ return this.filtered.slice(this.startIndex, this.endIndex); },
    compute(){ this.page = Math.min(this.page, this.totalPages); },
    go(n){ this.page = n; },
    prev(){ if(this.page>1) this.page--; },
    next(){ if(this.page<this.totalPages) this.page++; },
  }
}

// Alpine: Abandoned checkouts from localStorage
// Expects localStorage.setItem('abandoned_checkouts', JSON.stringify([{ ts: 1710000000000, cartSize: 3 }, ...]))
function abandonedWidget({ serverValue = null } = {}) {
  return {
    count: serverValue, // null until computed
    init() {
      try {
        const raw = localStorage.getItem('abandoned_checkouts') || '[]';
        const arr = JSON.parse(raw);
        const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
        this.count = arr.filter(e => Number(e?.ts) >= weekAgo).length;
      } catch (e) {
        this.count = serverValue ?? null;
      }
    }
  }
}
</script>
@endsection
