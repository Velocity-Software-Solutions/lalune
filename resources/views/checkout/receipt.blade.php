<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('receipt.title', ['number' => $order->order_number]) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 14px; }
        h1 { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: {{ app()->getLocale()==='ar' ? 'right' : 'left' }}; }
        .muted { color: #555; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ __('receipt.heading', ['number' => $order->order_number]) }}</h1>
    <p class="muted">
        <strong>{{ __('receipt.customer') }}:</strong>
        {{ $order->full_name }} ({{ $order->email }})
    </p>
    <p class="muted">
        <strong>{{ __('receipt.order_date') }}:</strong>
        {{ $order->created_at->translatedFormat('F j, Y') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>{{ __('receipt.th_product') }}</th>
                <th>{{ __('receipt.th_qty') }}</th>
                <th>{{ __('receipt.th_price') }}</th>
                <th>{{ __('receipt.th_subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? __('receipt.deleted_item') }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ __('product.currency_aed') }} {{ number_format($item->price, 2) }}</td>
                    <td>{{ __('product.currency_aed') }} {{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total" style="margin-top:16px;">
        <strong>{{ __('receipt.total_amount') }}:</strong>
        {{ __('product.currency_aed') }} {{ number_format($order->total_amount, 2) }}
    </p>
</body>
</html>
