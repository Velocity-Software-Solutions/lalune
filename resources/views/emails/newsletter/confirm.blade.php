@php
    $brandName = 'LaLune by NE';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm your subscription | {{ $brandName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f5f5f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                       style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <!-- Header -->
                    <tr>
                        <td style="padding:22px 26px; background-color:#000000; color:#f9fafb; text-align:left;">
                            <div style="font-size:20px; font-weight:600; letter-spacing:0.18em; text-transform:uppercase;">
                                {{ $brandName }}
                            </div>
                            <div style="margin-top:6px; font-size:12px; opacity:0.8;">
                                Confirm your email subscription
                            </div>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:26px;">
                            <p style="margin:0 0 12px; font-size:14px; color:#111827;">
                                Hi,
                            </p>

                            <p style="margin:0 0 14px; font-size:14px; color:#333333; line-height:1.6;">
                                Thank you for subscribing to the <strong>{{ $brandName }}</strong> newsletter.
                                Please confirm your email address to start receiving exclusive updates and promo codes.
                            </p>

                            <p style="margin:0 0 20px; font-size:13px; color:#6b7280; line-height:1.6;">
                                One click is all it takes to confirm it was really you.
                            </p>

                            <!-- Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $confirmUrl }}"
                                           style="display:inline-block; padding:10px 26px; font-size:13px; font-weight:600;
                                                  color:#ffffff; text-decoration:none; border-radius:9999px;
                                                  background-color:#000000; border:1px solid #000000;">
                                            Confirm subscription
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 10px; font-size:12px; color:#6b7280; line-height:1.6;">
                                If you didn’t request this, you can safely ignore this email and you won’t be subscribed.
                            </p>

                            <p style="margin:0; font-size:11px; color:#9ca3af;">
                                This link will only work once.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 26px; border-top:1px solid #e5e7eb; background-color:#fafafa;">
                            <p style="margin:0; font-size:11px; color:#9ca3af; line-height:1.5;">
                                &copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.
                            </p>
                            <p style="margin:4px 0 0; font-size:11px; color:#9ca3af;">
                                You received this email because your address was used to sign up for our newsletter.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
