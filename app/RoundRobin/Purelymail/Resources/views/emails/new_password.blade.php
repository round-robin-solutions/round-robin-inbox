<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Your new Round Robin password') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fa;font-family:Inter,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#050b18">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa">
<tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#050b18;padding:22px 28px;color:#f4f7fa;font-family:'Space Grotesk',Inter,Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;letter-spacing:.2px">Round Robin</td></tr>
    <tr><td style="height:4px;line-height:4px;font-size:0;background:#9b7cff;background:linear-gradient(90deg,#9b7cff,#54c7f5,#5ef2b2)">&nbsp;</td></tr>
    <tr><td style="padding:28px">
        <p style="margin:0 0 14px;font-size:16px">Hi {{ $d['first_name'] }},</p>
<p style="margin:0 0 18px;font-size:15px;line-height:1.55">{{ $d['by'] }} set a new password for your Round Robin login.@if ($d['backed']) It works everywhere: webmail, mail apps on your phone and the team inbox.@endif</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;border-radius:8px;margin:0 0 22px">
<tr><td style="padding:14px 16px 4px;font-size:13px;color:#4a5668">Email</td></tr>
<tr><td style="padding:0 16px 10px;font-size:17px;font-weight:600">{{ $d['address'] }}</td></tr>
<tr><td style="padding:4px 16px 4px;font-size:13px;color:#4a5668">New password</td></tr>
<tr><td style="padding:0 16px 14px;font-size:17px;font-weight:600;font-family:Menlo,Consolas,monospace;letter-spacing:.5px">{{ $d['password'] }}</td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 10px"><tr><td style="border-radius:8px;background:#050b18"><a href="{{ $d['dashboard'] }}" style="display:inline-block;padding:11px 18px;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px">Sign in to the team inbox</a></td></tr></table>
@if ($d['backed'])<p style="margin:14px 0 0;font-size:14px;line-height:1.55">If your phone's mail app asks for your password, enter the new one there too.</p>@endif
<p style="margin:14px 0 0;font-size:14px;line-height:1.55">Please change it after you sign in: click your name, then <strong>Change password</strong>.</p>
<p style="margin:24px 0 0;font-size:14px;color:#4a5668">Didn't expect this? Reply to this email.</p>
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #e3e8ee;font-size:12px;color:#7a8696">Round Robin Solutions</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
