<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Reset your Round Robin password') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fa;font-family:Inter,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#050b18">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa">
<tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#050b18;padding:22px 28px;color:#f4f7fa;font-family:'Space Grotesk',Inter,Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;letter-spacing:.2px">Round Robin</td></tr>
    <tr><td style="height:4px;line-height:4px;font-size:0;background:#9b7cff;background:linear-gradient(90deg,#9b7cff,#54c7f5,#5ef2b2)">&nbsp;</td></tr>
    <tr><td style="padding:28px">
        <p style="margin:0 0 14px;font-size:16px">Hi {{ $d['first_name'] }},</p>
<p style="margin:0 0 18px;font-size:15px;line-height:1.55">Someone asked to reset the password for <strong>{{ $d['address'] }}</strong>. If that was you, choose a new one here. The link works for 60 minutes.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 10px"><tr><td style="border-radius:8px;background:#050b18"><a href="{{ $d['url'] }}" style="display:inline-block;padding:11px 18px;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px">Choose a new password</a></td></tr></table>
@if ($d['backed'])<p style="margin:14px 0 0;font-size:14px;line-height:1.55">The new password works for webmail and your phone's mail app too; update it there.</p>@endif
<p style="margin:24px 0 0;font-size:14px;color:#4a5668">Didn't ask for this? Ignore this email; your password stays the same.</p>
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #e3e8ee;font-size:12px;color:#7a8696">Round Robin Solutions</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
