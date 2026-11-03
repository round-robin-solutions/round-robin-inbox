<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Your Round Robin email and inbox login') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fa;font-family:Inter,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#050b18">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa">
<tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#050b18;padding:22px 28px;color:#f4f7fa;font-family:'Space Grotesk',Inter,Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;letter-spacing:.2px">Round Robin</td></tr>
    <tr><td style="height:4px;line-height:4px;font-size:0;background:#9b7cff;background:linear-gradient(90deg,#9b7cff,#54c7f5,#5ef2b2)">&nbsp;</td></tr>
    <tr><td style="padding:28px">
        <p style="margin:0 0 14px;font-size:16px">Hi {{ $d['first_name'] }},</p>
        <p style="margin:0 0 20px;font-size:15px;line-height:1.55">Your Round Robin email is ready. One password works everywhere: webmail, mail apps on your phone and the team inbox.</p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;border-radius:8px;margin:0 0 22px">
            <tr><td style="padding:14px 16px 4px;font-size:13px;color:#4a5668">Email</td></tr>
            <tr><td style="padding:0 16px 10px;font-size:17px;font-weight:600">{{ $d['address'] }}</td></tr>
            <tr><td style="padding:4px 16px 4px;font-size:13px;color:#4a5668">Password</td></tr>
            @if ($d['password'])
            <tr><td style="padding:0 16px 14px;font-size:17px;font-weight:600;font-family:Menlo,Consolas,monospace;letter-spacing:.5px">{{ $d['password'] }}</td></tr>
            @else
            <tr><td style="padding:0 16px 14px;font-size:15px">Your current email password</td></tr>
            @endif
        </table>

        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 10px"><tr>
            <td style="border-radius:8px;background:#050b18"><a href="{{ $d['webmail'] }}" style="display:inline-block;padding:11px 18px;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px">Open webmail</a></td>
            <td style="width:10px"></td>
            <td style="border-radius:8px;border:1px solid #050b18"><a href="{{ $d['dashboard'] }}" style="display:inline-block;padding:10px 17px;color:#050b18;text-decoration:none;font-weight:600;font-size:15px">Open team inbox</a></td>
        </tr></table>
        @if (count($d['mailboxes']))
        <p style="margin:6px 0 0;font-size:14px;line-height:1.5;color:#4a5668">In the team inbox you can see: {{ implode(', ', $d['mailboxes']) }}.</p>
        @endif

        <p style="margin:22px 0 6px;font-size:15px;font-weight:600">On your phone</p>
        <p style="margin:0;font-size:14px;line-height:1.55">Add an account in your mail app (Gmail, Outlook or Apple Mail) with your full email address and password. If it asks for server settings: incoming IMAP <strong>{{ $d['imap'] }}</strong>, outgoing SMTP <strong>{{ $d['smtp'] }}</strong>, SSL on.</p>

        @if ($d['password'])
        <p style="margin:22px 0 6px;font-size:15px;font-weight:600">Change your password</p>
        <p style="margin:0;font-size:14px;line-height:1.55">After you first sign in to the team inbox, click your name, then <strong>Change password</strong>. That changes it for webmail and your phone too.</p>
        @endif
        @if ($d['lost_password'])
        <p style="margin:14px 0 0;font-size:14px;line-height:1.55;color:#4a5668">Forgot it later? <a href="{{ $d['lost_password'] }}" style="color:#050b18">Reset it here</a> with the personal email this was sent to.</p>
        @endif

        <p style="margin:24px 0 0;font-size:14px;color:#4a5668">Questions? Just reply to this email.</p>
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #e3e8ee;font-size:12px;color:#7a8696">Round Robin Solutions</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
