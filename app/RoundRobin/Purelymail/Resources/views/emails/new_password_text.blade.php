Hi {{ $d['first_name'] }},

{{ $d['by'] }} set a new password for your Round Robin login.@if ($d['backed']) It works everywhere: webmail, mail apps on your phone and the team inbox.@endif


Email: {{ $d['address'] }}
New password: {{ $d['password'] }}

Sign in: {{ $d['dashboard'] }}
@if ($d['backed'])
If your phone's mail app asks for your password, enter the new one there too.
@endif

Please change it after you sign in: click your name, then Change password.

Didn't expect this? Reply to this email.

Round Robin Solutions
