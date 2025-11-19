<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;
class DashboardController extends Controller
{


public function index()
{
    $now      = now();
    $from30   = $now->copy()->subDays(30);
    $from7    = $now->copy()->subDays(7);

    // ===== KPIs (uses payment_status + *_cents)
    $kpis = [
        'sales_30d'     => (float) Order::paid()
                                ->whereBetween('created_at', [$from30, $now])
                                ->sum('total_cents') / 100, // convert cents → major unit
        'orders_today'  => (int) Order::whereDate('created_at', $now->toDateString())->count(),
        'pending_count' => (int) Order::where('order_status', 'pending')->count(),
        'refunds_30d'   => (int) Order::where('order_status', 'refunded')
                                ->whereBetween('updated_at', [$from30, $now])
                                ->count(),
    ];

    // ===== Orders last 30 days (chart) — count of PAID orders per day
    $rows = Order::paid()
        ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
        ->whereBetween('created_at', [$from30->toDateString(), $now->toDateString()])
        ->groupBy('d')->orderBy('d')
        ->pluck('c', 'd'); // ['YYYY-MM-DD' => N]

    $labels = [];
    $data   = [];
    for ($d = $from30->copy(); $d <= $now; $d->addDay()) {
        $key = $d->toDateString();
        $labels[] = $key;
        $data[]   = (int) ($rows[$key] ?? 0);
    }
    $ordersSeries = ['labels' => $labels, 'data' => $data];

    // ===== Pending orders table (map to your columns)
    // - uses: order_number, full_name, email, payment_method, total_cents
    // - uses withCount('items') to avoid N+1
    $pendingOrders = Order::where('order_status', 'pending')
        ->withCount('items')
        ->latest()
        ->take(100)
        ->get()
        ->map(function ($o) {
            return [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'customer_name'  => $o->full_name ?? '—',
                'customer_email' => $o->email ?? '—',
                'items_count'    => (int) ($o->items_count ?? 0),
                'total'          => (float) ($o->total_cents / 100),  // number for frontend formatting
                'payment_method' => $o->payment_method ?? '—',
                'placed'         => optional($o->created_at)->format('M d, Y'),
            ];
        })
        ->all();

    // ===== Insights
    // New customers = distinct emails whose FIRST order (any status) happened in last 7 days
    // (emails normalized to lowercase; ignore null/empty)
    $firstOrdersSub = DB::table('orders')
        ->whereNotNull('email')->where('email', '<>', '')
        ->selectRaw('LOWER(email) as email, MIN(created_at) as first_at')
        ->groupBy('email');

    $newCustomers7d = DB::query()
        ->fromSub($firstOrdersSub, 't')
        ->whereBetween('first_at', [$from7, $now])
        ->count();

    // Low stock (simple numeric threshold = 5 units)
    $lowStockNames = Product::select('name')
        ->where('stock_quantity', '<=', 5)
        ->orderBy('stock_quantity')
        ->limit(5)
        ->pluck('name')
        ->all();

    $insights = [
        'low_stock'               => $lowStockNames,
        'low_stock_count'         => count($lowStockNames),
        'new_customers_7d'        => $newCustomers7d,
        'abandoned_checkouts_7d'  => null, // stays client-side via localStorage
    ];

    $indexHero = GeneralSetup::where('key', 'index_hero')->first();


    return view('admin.dashboard', compact('kpis', 'ordersSeries', 'pendingOrders', 'insights','indexHero'));
}
}
