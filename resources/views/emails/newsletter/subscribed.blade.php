@php
    $brandName = 'LaLune by NE';
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Welcome to our newsletter | {{ $brandName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body
    style="margin:0; padding:0; background-color:#f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
        style="background-color:#f5f5f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <!-- Header -->
                    <tr>
                        <td style="padding:10px 16px; background-color:#000000; color:#f9fafb;">
                            <div style="display:flex; justify-content:center;">
                                <img src="{{ asset('images/logo-horizontal.jpg') }}" alt="Lalune By NE Logo"
                                    class="h-[80px] lg:h-[90px] xl:h-[100px]" height="80px">
                            </div>
                            <div style="margin-top:6px; font-size:12px; opacity:0.8; text-align: center;">
                                You’re now subscribed
                            </div>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:26px;">
                            <p style="margin:0 0 12px; font-size:14px; color:#111827;">
                                Welcome,
                            </p>

                            <p style="margin:0 0 14px; font-size:14px; color:#333333; line-height:1.6;">
                                You’re officially part of the <strong>{{ $brandName }}</strong> list.
                                We’ll send you exclusive updates, early looks, and special promo codes from time to
                                time.
                            </p>

                            @isset($promoCode)
                                <p style="margin:10px 0 10px; font-size:13px; color:#111827;">
                                    Here’s a little something to start:
                                </p>

                                <p
                                    style="margin:0 0 18px; font-size:18px; font-weight:700; letter-spacing:0.16em;
                                          color:#000000; text-align:center; text-transform:uppercase;">
                                    {{ $promoCode }}
                                </p>

                                <p style="margin:0 0 20px; font-size:12px; color:#6b7280; text-align:center;">
                                    Apply this code at checkout on your next order.
                                </p>
                            @endisset

                            <p style="margin:0 0 18px; font-size:13px; color:#6b7280; line-height:1.6;">
                                We’ll keep things curated and occasional — no spam, just the essentials.
                            </p>

                            @isset($shopUrl)
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $shopUrl }}"
                                                style="display:inline-block; padding:10px 26px; font-size:13px; font-weight:600;
                                                      color:#ffffff; text-decoration:none; border-radius:9999px;
                                                      background-color:#000000; border:1px solid #000000;">
                                                Browse LaLune
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            <p style="margin:0; font-size:11px; color:#9ca3af; line-height:1.6;">
                                You can unsubscribe anytime using the link at the bottom of any email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 26px; border-top:1px solid #e5e7eb; background-color:#fafafa;">
                            <p style="margin:0; font-size:11px; color:#9ca3af; line-height:1.5;">
                                &copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.
                            </p>

                            @isset($unsubscribeUrl)
                                <p style="margin:4px 0 0; font-size:11px; color:#9ca3af;">
                                    Prefer not to receive these?
                                    <a href="{{ $unsubscribeUrl }}" style="color:#4b5563; text-decoration:underline;">
                                        Unsubscribe here
                                    </a>.
                                </p>
                            @endisset
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
