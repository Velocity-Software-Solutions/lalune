@component('mail::message')
    # Order Confirmed – {{ $order->order_number }}

    Thank you for your order!

    **Total:** ${{ number_format($order->total_amount, 2) }}

    **Shipping Address:**
    {{ $order->shipping_address }}

    @component('mail::panel')
        We’re preparing your items and will notify you once they’re shipped.
    @endcomponent

    @component('mail::button', ['url' => route('checkout.receipt', $order->id)])
        Download Receipt (PDF)
    @endcomponent

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
