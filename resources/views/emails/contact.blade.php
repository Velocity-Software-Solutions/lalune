<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Contact Form Submission</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background-color: #f9f7f4;">

    <table align="center" width="600" cellpadding="0" cellspacing="0" style="border-collapse: collapse; background-color: #ffffff; box-shadow: 0 0 10px rgba(0,0,0,0.05);">
        <tr>
            <td align="center" bgcolor="#4b3621" style="padding: 30px 0; color: #ffffff;">
                <h1 style="margin: 0; font-size: 24px;">Al Khinjar Al Dhahbi</h1>
                <p style="margin: 0; font-size: 14px;">New Contact Form Message</p>
            </td>
        </tr>

        <tr>
            <td style="padding: 30px; color: #333;">
                <h2 style="color: #4b3621; margin-bottom: 20px;">Contact Details</h2>
                <p><strong>Name:</strong> {{ $details['name'] }}</p>
                <p><strong>Email:</strong> {{ $details['email'] }}</p>
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
                <h3 style="color: #4b3621; margin-bottom: 10px;">Message:</h3>
                <p style="font-size: 16px; line-height: 1.5;">{{ $details['message'] }}</p>
            </td>
        </tr>

        <tr>
            <td bgcolor="#f2efea" style="padding: 20px; text-align: center; font-size: 12px; color: #777;">
                This message was sent from the website contact form.<br>
                © {{ date('Y') }} Al Khinjar Al Dhahbi. All rights reserved.
            </td>
        </tr>
    </table>

</body>
</html>
