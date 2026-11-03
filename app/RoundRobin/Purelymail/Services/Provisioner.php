<?php

namespace App\RoundRobin\Purelymail\Services;

use App\Mailbox;

/**
 * Puts a FreeScout mailbox on Purelymail: creates the Purelymail user when it doesn't exist yet,
 * makes a dedicated app password, and fills in the IMAP fetch + SMTP send settings.
 */
class Provisioner
{
    private $api;

    public function __construct(PurelymailApi $api)
    {
        $this->api = $api;
    }

    public static function eligible(Mailbox $mailbox)
    {
        $domain = strtolower(substr(strrchr($mailbox->email, '@'), 1));

        return in_array($domain, config('purelymail.domains')) && !$mailbox->in_server;
    }

    /** @return string|null the new webmail password when a Purelymail user was created, else null */
    public function provision(Mailbox $mailbox)
    {
        $address = strtolower($mailbox->email);
        [$local, $domain] = explode('@', $address, 2);
        $password = null;
        if (!in_array($address, array_map('strtolower', $this->api->users()))) {
            $password = self::randomPassword();
            $this->api->createUser($local, $domain, $password);
        }
        $this->gmailStyleAlias($address);
        $this->configure($mailbox);

        return $password;
    }

    /**
     * Gmail-like addressing (Rahul, 2026-11-03). Purelymail with symbolic subaddressing off treats
     * first.last@ and firstlast@ as two addresses and does not strip +tags (they hit the catch-all),
     * so every address gets routing rules:
     *   local+...      (prefix) -> address
     *   dotless        (exact)  -> address   (only for dotted locals)
     *   dotless+...    (prefix) -> address   (only for dotted locals)
     * A rule is skipped when the match is already a user or already routed.
     */
    public function gmailStyleAlias($address)
    {
        $address = strtolower($address);
        [$local, $domain] = explode('@', $address, 2);
        $want = [[$local.'+', true]];
        $plain = str_replace('.', '', $local);
        if ($plain !== $local && !in_array($plain.'@'.$domain, array_map('strtolower', $this->api->users()))) {
            $want[] = [$plain, false];
            $want[] = [$plain.'+', true];
        }
        $have = [];
        foreach ($this->api->routingRules() as $rule) {
            if (!$rule['catchall'] && $rule['domainName'] === $domain) {
                $have[strtolower($rule['matchUser']).'|'.(int) $rule['prefix']] = true;
            }
        }
        $added = [];
        foreach ($want as [$match, $prefix]) {
            if (empty($have[$match.'|'.(int) $prefix])) {
                $this->api->addRule($match, $prefix, $domain, $address);
                $added[] = $match.($prefix ? '*' : '');
            }
        }

        return $added;
    }

    public function configure(Mailbox $mailbox)
    {
        $address = strtolower($mailbox->email);
        $app_password = $this->api->appPassword($address, 'FreeScout inbox.roundrobin.in');
        $imap = config('purelymail.imap');
        $smtp = config('purelymail.smtp');
        $mailbox->fill([
            'in_protocol'      => Mailbox::IN_PROTOCOL_IMAP,
            'in_server'        => $imap['server'],
            'in_port'          => $imap['port'],
            'in_encryption'    => Mailbox::IN_ENCRYPTION_SSL,
            'in_validate_cert' => true,
            'in_username'      => $address,
            'in_password'      => $app_password,
            'out_method'       => Mailbox::OUT_METHOD_SMTP,
            'out_server'       => $smtp['server'],
            'out_port'         => $smtp['port'],
            'out_encryption'   => Mailbox::OUT_ENCRYPTION_SSL,
            'out_username'     => $address,
            'out_password'     => $app_password,
            'imap_sent_folder' => config('purelymail.sent'),
        ]);
        $mailbox->setInImapFolders(['INBOX']);
        $mailbox->save();

        return $mailbox;
    }

    public static function randomPassword()
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 24; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
