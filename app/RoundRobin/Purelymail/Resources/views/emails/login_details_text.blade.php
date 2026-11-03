Hi {{ $d['first_name'] }},

Your Round Robin email is ready. One password works everywhere: webmail, mail apps on your phone and the team inbox.

Email: {{ $d['address'] }}
@if ($d['password'])
Password: {{ $d['password'] }}
@else
Password: your current email password
@endif

Read and send mail: {{ $d['webmail'] }}
Team inbox: {{ $d['dashboard'] }}
@if (count($d['mailboxes']))
In the team inbox you can see: {{ implode(', ', $d['mailboxes']) }}.
@endif

Phone mail app (Gmail, Outlook, Apple Mail): add an account with your full email address and password.
If it asks for server settings: incoming IMAP {{ $d['imap'] }}, outgoing SMTP {{ $d['smtp'] }}, SSL on.

@if ($d['password'])
Please change your password after you first sign in: in the team inbox, click your name, then Change password. That changes it for webmail and your phone too.

@endif
@if ($d['lost_password'])
Forgot it later? Reset it here with this personal email address: {{ $d['lost_password'] }}

@endif
Questions? Just reply to this email.

Round Robin Solutions
