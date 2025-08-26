@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
<div class="px-4 py-6 space-y-6 overflow-scroll custom-scrollbar scrollbar-hide">

  {{-- ===== KPIs ===== --}}
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Total sales (30d)</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">72,400.00</p>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Orders today</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">307</p>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Pending orders</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">54</p>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-500">Refunds (30d)</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900">3</p>
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
      x-data="pendingOrdersTable()"
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
                    <span x-text="o.customer_name"></span>
                    <span class="text-gray-500 text-xs" x-text="o.customer_email"></span>
                  </div>
                </td>
                <td class="px-4 py-3" x-text="o.items_count"></td>
                <td class="px-4 py-3 text-right" x-text="'AED ' + o.total.toFixed(2)"></td>
                <td class="px-4 py-3" x-text="o.payment_method"></td>
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
          <span x-text="startIndex + 1"></span>–
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
            <p class="text-gray-500 text-sm">Headphones, Desk lamp</p>
          </div>
          <span class="text-xs px-2 py-1 bg-gray-100 rounded">2 SKUs</span>
        </li>
        <li class="flex items-start justify-between">
          <div>
            <p class="font-medium text-gray-800">New customers</p>
            <p class="text-gray-500 text-sm">Last 7 days</p>
          </div>
          <span class="text-xs px-2 py-1 bg-gray-100 rounded">13</span>
        </li>
        <li class="flex items-start justify-between">
          <div>
            <p class="font-medium text-gray-800">Abandoned checkouts</p>
            <p class="text-gray-500 text-sm">Last 7 days</p>
          </div>
          <span class="text-xs px-2 py-1 bg-gray-100 rounded">7</span>
        </li>
      </ul>
    </div>
  </div>
</div>
  {{-- AlpineJS for table pagination/search --}}
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>

  <script>
  // ===== Chart example data =====
  document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('ordersChart');
    const labels = [
      'Aug 1','Aug 2','Aug 3','Aug 4','Aug 5','Aug 6','Aug 7','Aug 8','Aug 9','Aug 10',
      'Aug 11','Aug 12','Aug 13','Aug 14','Aug 15','Aug 16','Aug 17','Aug 18','Aug 19','Aug 20',
      'Aug 21','Aug 22','Aug 23','Aug 24','Aug 25','Aug 26','Aug 27','Aug 28','Aug 29','Aug 30'
    ];
    const data = [72,58,63,65,60,74,69,70,66,61,59,62,64,88,76,70,65,71,80,73,68,75,72,70,78,85,74,76,69,82];

    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Orders',
          data,
          fill: true,
          tension: 0.35,
          borderWidth: 2,
          borderColor: 'rgba(17,24,39,0.9)',     // gray-900
          backgroundColor: 'rgba(17,24,39,0.08)',// subtle fill
          pointRadius: 2,
          pointHoverRadius: 4,
          pointStyle: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { grid: { display: false }, ticks: { color: '#6b7280' }}, // gray-500
          y: { grid: { color: 'rgba(209,213,219,0.5)' }, ticks: { color: '#6b7280' }}
        },
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }
      }
    });
  });

  // ===== Pending Orders: example data + search/pagination (client-side) =====
  function pendingOrdersTable() {
    return {
      // example data (all status = 'pending')
      orders: [
        { id:1, order_number:'82301601', customer_name:'John Brown',  customer_email:'john@example.com',  items_count:1, total:  99.99, payment_method:'Visa', placed:'Aug 26, 2025' },
        { id:2, order_number:'82301602', customer_name:'Ava Lee',     customer_email:'ava@example.com',   items_count:2, total: 248.99, payment_method:'Visa', placed:'Aug 27, 2025' },
        { id:3, order_number:'82801603', customer_name:'John Smith',  customer_email:'johns@example.com', items_count:1, total: 249.99, payment_method:'COD',  placed:'Aug 28, 2025' },
        { id:4, order_number:'82801604', customer_name:'Sara Ali',    customer_email:'sara@example.com',  items_count:3, total: 399.00, payment_method:'Apple Pay', placed:'Aug 28, 2025' },
        { id:5, order_number:'82801605', customer_name:'Hamad Noor',  customer_email:'hamad@example.com', items_count:1, total:  59.00, payment_method:'Visa', placed:'Aug 29, 2025' },
        { id:6, order_number:'82801606', customer_name:'Noura Zed',   customer_email:'noura@example.com', items_count:4, total: 529.00, payment_method:'Visa', placed:'Aug 29, 2025' },
        { id:7, order_number:'82801607', customer_name:'Omar Khan',   customer_email:'omar@example.com',  items_count:2, total: 189.50, payment_method:'COD',  placed:'Aug 30, 2025' },
        { id:8, order_number:'82801608', customer_name:'Fatma Adel',  customer_email:'fatma@example.com', items_count:1, total:  89.00, payment_method:'Visa', placed:'Aug 30, 2025' },
        { id:9, order_number:'82801609', customer_name:'Lina Park',   customer_email:'lina@example.com',  items_count:2, total: 220.00, payment_method:'PayPal', placed:'Aug 30, 2025' },
        { id:10,order_number:'82801610', customer_name:'Ali Ahmed',   customer_email:'ali@example.com',   items_count:1, total: 129.00, payment_method:'Visa', placed:'Aug 30, 2025' },
      ],

      // ui state
      q: '',
      page: 1,
      perPage: 5,
      init(){ this.compute(); },

      // derived
      get filtered() {
        if (!this.q) return this.orders;
        const q = this.q.toLowerCase();
        return this.orders.filter(o =>
          (o.order_number+'').toLowerCase().includes(q) ||
          (o.customer_name||'').toLowerCase().includes(q) ||
          (o.customer_email||'').toLowerCase().includes(q)
        );
      },
      get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
      get startIndex(){ return (this.page - 1) * this.perPage; },
      get endIndex(){ return Math.min(this.filtered.length, this.page * this.perPage); },
      get paginated(){ return this.filtered.slice(this.startIndex, this.endIndex); },

      // actions
      compute(){ this.page = Math.min(this.page, this.totalPages); },
      go(n){ this.page = n; },
      prev(){ if(this.page>1) this.page--; },
      next(){ if(this.page<this.totalPages) this.page++; },

      // watchers
      // Alpine v3: use effect via setters in template (x-model triggers recompute automatically)
    }
  }
  </script>
@endsection
