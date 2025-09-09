<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;
class DashboardController extends Controller
{


public function index()
{
    $now   = now();
    $from30 = $now->copy()->subDays(30);
    $from7  = $now->copy()->subDays(7);

    // ===== KPIs
    $kpis = [
        'sales_30d'     => (float) Order::paid()
                                ->whereBetween('created_at', [$from30, $now])
                                ->sum('grand_total'),
        'orders_today'  => (int) Order::whereDate('created_at', $now->toDateString())->count(),
        'pending_count' => (int) Order::where('status', 'pending')->count(),
        'refunds_30d'   => (int) Order::where('status', 'refunded')
                                ->whereBetween('updated_at', [$from30, $now])
                                ->count(), // or your Refund model if you have one
    ];

    // ===== Orders last 30 days (chart)
    $rows = Order::paid()
        ->selectRaw('DATE(created_at) d, COUNT(*) c')
        ->whereBetween('created_at', [$from30->toDateString(), $now->toDateString()])
        ->groupBy('d')->orderBy('d')->pluck('c','d');

    $labels = [];
    $data   = [];
    for ($d = $from30->copy(); $d <= $now; $d->addDay()) {
        $key = $d->toDateString();
        $labels[] = $key;
        $data[]   = (int) ($rows[$key] ?? 0);
    }
    $ordersSeries = ['labels' => $labels, 'data' => $data];

    // ===== Pending orders table (use order billing/shipping fields)
    // Adjust field names to yours: billing_name, billing_email, etc.
    $pendingOrders = Order::where('status','pending')
        ->latest()->take(100)
        ->get()
        ->map(function($o){
            return [
                'id'             => $o->id,
                'order_number'   => $o->number,             // adjust
                'customer_name'  => $o->billing_name ?? '—',// adjust
                'customer_email' => $o->billing_email ?? '—',// adjust
                'items_count'    => (int) ($o->items_count ?? $o->items()->count()),
                'total'          => (float) $o->grand_total,
                'payment_method' => $o->payment_method ?? '—',
                'placed'         => $o->created_at->format('M d, Y'),
            ];
        })->all();

    // ===== Insights
    // NEW customers = distinct emails whose FIRST order happened in the last 7 days
    // (ignores null/empty emails)
    $newCustomers7d = DB::table('orders')
        ->whereNotNull('billing_email')
        ->where('billing_email','<>','')
        ->selectRaw('LOWER(billing_email) as email, MIN(created_at) as first_at')
        ->groupBy('email')
        ->havingBetween('first_at', [$from7, $now])
        ->count();

    // Low stock as before (optional)
    $lowStockNames = Product::select('name')
        ->whereColumn('stock','<=','10')
        ->orderBy('stock')->limit(5)->pluck('name')->all();

    $insights = [
        'low_stock'               => $lowStockNames,
        'low_stock_count'         => count($lowStockNames),
        'new_customers_7d'        => $newCustomers7d,
        // Abandoned checkouts come from browser storage; server can’t know it:
        'abandoned_checkouts_7d'  => null, // will be filled on the client
    ];

    return view('admin.dashboard', compact('kpis','ordersSeries','pendingOrders','insights'));
}


}
