<?php

return [
    // API token file, bind-mounted read-only by compose.yml (never in the database or the repo).
    'token_file' => '/run/secrets/purelymail_token',
    // Domains new inboxes may be created on (must be added in Purelymail and routed to it).
    'domains'    => ['roundrobin.in'],
    // Addresses that stay personal and are never offered for connecting.
    'personal'   => ['rahul@roundrobin.in', 'founder@roundrobin.in', 'mailtest@roundrobin.in', 'noreply@roundrobin.in', 'roundrobin@purelymail.com'],
    'imap'       => ['server' => 'imap.purelymail.com', 'port' => 993],
    'smtp'       => ['server' => 'smtp.purelymail.com', 'port' => 465],
    'sent'       => 'Sent',
    // Links in the first-login email for a new person.
    'webmail'       => 'https://inbox.purelymail.com/',
    'lost_password' => 'https://purelymail.com/user/lostPassword',
    // Replies to the first-login email go to the team that helps with accounts.
    'login_details_reply_to' => 'tech@roundrobin.in',
];
