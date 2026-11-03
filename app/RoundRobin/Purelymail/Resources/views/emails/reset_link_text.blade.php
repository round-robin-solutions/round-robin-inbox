Hi {{ $d['first_name'] }},

Someone asked to reset the password for {{ $d['address'] }}. If that was you, choose a new one here (the link works for 60 minutes):

{{ $d['url'] }}

@if ($d['backed'])
The new password works for webmail and your phone's mail app too; update it there.

@endif
Didn't ask for this? Ignore this email; your password stays the same.

Round Robin Solutions
