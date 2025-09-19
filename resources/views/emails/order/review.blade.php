@php
    /** @var \App\Models\Order $order */
    /** @var \Illuminate\Support\Collection|\App\Models\OrderItem[] $items */
    $customer = $order->full_name ?: 'there';
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your feedback matters</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f6f6f6;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f6f6f6;">
    <tr>
      <td align="center" style="padding:24px;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="padding:24px 24px 12px 24px;font-family:Arial,Helvetica,sans-serif;">
              <h1 style="margin:0 0 8px 0;font-size:20px;line-height:28px;color:#111;">Hi {{ $customer }},</h1>
              <p style="margin:0 0 16px 0;font-size:14px;line-height:20px;color:#444;">
                We hope you’re loving your purchase! If you have a minute, we’d really appreciate a quick review.
              </p>
              @if($order->order_number)
                <p style="margin:0 0 16px 0;font-size:12px;line-height:18px;color:#777;">
                  Order <strong>#{{ $order->order_number }}</strong>
                </p>
              @endif
            </td>
          </tr>

          {{-- Items --}}
          @foreach($items as $item)
            @php
                $product = $item->product; // may be null if deleted
                $name = $item->name ?? optional($product)->name ?? 'Product';
                $thumb = optional($item->snapshot)['image_url'] ?? null;
                // Fallback guess if you store images under product relation:
                if (!$thumb && $product && $product->images->count()) {
                    $thumb = asset('storage/' . $product->images->first()->image_path);
                }
                // Where to send them to review—product page with #reviews anchor
                $reviewUrl = $product
                    ? route('products.show', $product->slug) . '#reviews'
                    : url('/');
            @endphp
            <tr>
              <td style="padding:12px 24px;font-family:Arial,Helvetica,sans-serif;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td valign="top" style="width:64px;padding-right:12px;">
                      @if($thumb)
                        <img src="{{ $thumb }}" width="64" height="64" style="display:block;border-radius:8px;object-fit:cover;" alt="">
                      @else
                        <div style="width:64px;height:64px;border-radius:8px;background:#eee;"></div>
                      @endif
                    </td>
                    <td valign="top" style="font-size:14px;line-height:20px;color:#111;">
                      <div style="font-weight:bold;margin-bottom:6px;">{{ $name }}</div>
                      <div style="margin-top:8px;">
                        <a href="{{ $reviewUrl }}"
                           style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:8px 12px;border-radius:8px;font-size:13px;">
                          Leave a review
                        </a>
                      </div>
                    </td>
                    <td align="right" valign="top" style="font-size:13px;line-height:20px;color:#444;white-space:nowrap;">
                      Qty: {{ (int) $item->quantity }}
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          @endforeach

          <tr>
            <td style="padding:16px 24px 24px 24px;font-family:Arial,Helvetica,sans-serif;">
              <p style="margin:0;font-size:12px;line-height:18px;color:#777;">
                Thanks so much for your feedback—every review helps us improve.
              </p>
            </td>
          </tr>
        </table>

        <div style="max-width:620px;margin-top:16px;font-family:Arial,Helvetica,sans-serif;color:#999;font-size:11px;line-height:16px;">
          © {{ date('Y') }} {{ config('app.name') }}
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
