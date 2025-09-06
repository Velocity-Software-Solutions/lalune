<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Contact Message</title>
  <style>
    @media only screen and (max-width:600px){
      .container{ width:100% !important; }
      .p-24{ padding:16px !important; }
      .stack td{ display:block !important; width:100% !important; }
    }
  </style>
</head>
<body style="margin:0;background:#f6f7fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;line-height:1.6;color:#0f172a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:28px 16px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" class="container" style="width:600px;max-width:100%;">
          <tr>
            <td style="background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;">
              
              <h1 style="margin:0 0 12px 0;font-size:20px;">New Contact Message</h1>
              <p style="margin:0 0 20px 0;color:#6b7280;font-size:14px;">
                You received a new message from your contact form.
              </p>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:18px;">
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;width:30%;color:#64748b;">Name</td>
                  <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;font-weight:600;">{{ $name }}</td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;">Email</td>
                  <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;font-weight:600;">
                    <a href="mailto:{{ $email }}" style="color:#0ea5e9;text-decoration:none;">{{ $email }}</a>
                  </td>
                </tr>
                @if(!empty($phone))
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;color:#64748b;">Phone</td>
                  <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;font-weight:600;">{{ $phone }}</td>
                </tr>
                @endif
              </table>

              <div style="margin-top:16px;">
                <div style="font-size:14px;color:#475569;font-weight:700;margin-bottom:6px;">Message</div>
                <div style="font-size:16px;color:#0f172a;white-space:pre-wrap;line-height:1.6;">
                   {!! nl2br(e($user_message)) !!}
                </div>
              </div>

            </td>
          </tr>
          <tr>
            <td style="text-align:center;color:#9aa3af;font-size:12px;padding:14px 6px;">
              © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
