    <!-- It is not the man who has too little, but the man who craves more, that is poor. - Seneca -->
    {{-- resources/views/emails/newsletter/campaign.blade.php --}}
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>{{ $campaign->subject }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        {{-- Basic email-safe styles --}}
        <style type="text/css">
            body {
                margin: 0;
                padding: 0;
                background-color: #f4f4f5;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            }

            table {
                border-spacing: 0;
                border-collapse: collapse;
            }

            img {
                border: 0;
                max-width: 100%;
                height: auto;
                display: block;
            }

            .wrapper {
                width: 100%;
                background-color: #f4f4f5;
            }

            .main {
                width: 100%;
                max-width: 640px;
                margin: 0 auto;
                background-color: #ffffff;
            }

            .header {
                background-color: #000000;
                color: #ffffff;
                padding: 20px 24px;
            }

            .header-text-small {
                font-size: 11px;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                opacity: 0.8;
            }

            .header-title {
                font-size: 16px;
                font-weight: 600;
                margin-top: 4px;
            }

            .header-meta {
                font-size: 11px;
                color: #d4d4d8;
            }

            .top-links {
                text-align: right;
                font-size: 11px;
                color: #a1a1aa;
            }

            .top-links a {
                color: #a1a1aa;
                text-decoration: underline;
            }

            .content {
                padding: 24px 24px 12px 24px;
                color: #111827;
                font-size: 14px;
                line-height: 1.6;
            }

            .subject-label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: #9ca3af;
                margin-bottom: 4px;
            }

            .subject-line {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 16px;
                color: #111827;
            }

            .body-html {
                font-size: 14px;
                line-height: 1.6;
                color: #111827;
            }

            .body-html a {
                color: #000000;
                text-decoration: underline;
            }

            .footer {
                padding: 16px 24px 24px 24px;
                font-size: 11px;
                color: #6b7280;
                background-color: #f9fafb;
            }

            .footer a {
                color: #000000;
                text-decoration: underline;
            }

            .unsubscribe-button {
                display: inline-block;
                padding: 6px 12px;
                border-radius: 9999px;
                border: 1px solid #e5e7eb;
                font-size: 11px;
                color: #4b5563;
                text-decoration: none;
                background-color: #ffffff;
            }

            .preheader {
                display: none !important;
                visibility: hidden;
                opacity: 0;
                color: transparent;
                height: 0;
                width: 0;
                overflow: hidden;
                mso-hide: all;
            }

            @media (max-width: 600px) {

                .header,
                .content,
                .footer {
                    padding-left: 16px !important;
                    padding-right: 16px !important;
                }
            }

            h1 {
                font-size: 2.25rem;
                /* 36px */
                font-weight: 700;
            }

            h2 {
                font-size: 1.875rem;
                /* 30px */
                font-weight: 600;
            }

            h3 {
                font-size: 1.5rem;
                /* 24px */
                font-weight: 600;
            }

            h4 {
                font-size: 1.25rem;
                /* 20px */
                font-weight: 500;
            }

            h5 {
                font-size: 1rem;
                /* 16px */
                font-weight: 500;
            }

            h6 {
                font-size: 0.875rem;
                /* 14px */
                font-weight: 500;
            }
        </style>
    </head>

    <body>

        {{-- Preheader text (shown in inbox preview, hidden in email body) --}}
        <span class="preheader">
            {{ $campaign->preheader ?? 'Exclusive updates and special moments from LaLune by NE.' }}
        </span>

        <table role="presentation" class="wrapper" width="100%">
            <tr>
                <td align="center">

                    <table role="presentation" class="main" width="100%">

                        {{-- Top small bar with "View in browser" + Unsubscribe link --}}
                        <tr>
                            <td style="padding: 8px 24px 4px 24px; background-color:#f4f4f5;">
                                <table role="presentation" width="100%">
                                    <tr>
                                        <td align="left" style="font-size:11px; color:#9ca3af;">
                                            @if (!empty($webviewUrl ?? null))
                                                <a href="{{ $webviewUrl }}"
                                                    style="color:#9ca3af; text-decoration:underline;">
                                                    View in browser
                                                </a>
                                            @endif
                                        </td>
                                        <td align="right" class="top-links">
                                            @if (!empty($unsubscribeUrl ?? null))
                                                <a href="{{ $unsubscribeUrl }}">Unsubscribe</a>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- Header --}}
                        <tr>
                            <td class="header">
                                <table role="presentation" width="100%">
                                    <tr>
                                        <td align="left">
                                            <div class="header-text-small">
                                                LaLune by NE
                                            </div>
                                            <div class="header-title">
                                                Newsletter
                                            </div>
                                        </td>
                                        <td align="right">
                                            <div class="header-meta">
                                                {{ config('mail.from.address') }}<br>
                                                To: {{ $subscriber->email }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- Content --}}
                        <tr>
                            <td class="content">
                                {{-- Subject --}}
                                <div class="subject-label">Subject</div>
                                <div class="subject-line">
                                    {{ $campaign->subject }}
                                </div>

                                {{-- Optional greeting --}}
                                {{-- You can remove this if your body already includes one --}}
                                <div style="margin-bottom: 12px; font-size:14px; color:#4b5563;">
                                    @php
                                        $email = $subscriber->email;
                                    @endphp
                                    Hello,
                                </div>

                                {{-- Body (Summernote HTML) --}}
                                <div class="body-html">
                                    {!! $campaign->body !!}
                                </div>
                            </td>
                        </tr>

                        {{-- Footer --}}
                        <tr>
                            <td class="footer">
                                <p style="margin:0 0 8px 0;">
                                    You’re receiving this email because you subscribed to the LaLune by NE newsletter.
                                </p>

                                @if (!empty($unsubscribeUrl ?? null))
                                    <p style="margin:0 0 12px 0;">
                                        If you no longer wish to receive our emails, you can
                                        <a href="{{ $unsubscribeUrl }}">unsubscribe here</a>.
                                    </p>
                                @endif

                                <p style="margin:0; color:#9ca3af;">
                                    &copy; {{ now()->year }} LaLune by NE. All rights reserved.
                                </p>
                            </td>
                        </tr>

                    </table>

                </td>
            </tr>
        </table>

    </body>

    </html>
