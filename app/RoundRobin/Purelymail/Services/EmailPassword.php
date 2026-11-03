<?php

namespace App\RoundRobin\Purelymail\Services;

/**
 * One password (Rahul, 2026-11-03): a person's dashboard password is their Purelymail email password.
 *
 * Purelymail is the source of truth. At sign-in the password is checked with an IMAP LOGIN; the
 * FreeScout hash is only a cached copy, so sign-in still works while Purelymail is unreachable.
 * Changing the password in the dashboard changes it on Purelymail too (Http/Middleware/EmailPassword).
 */
class EmailPassword
{
    const USERS_CACHE = 'purelymail.users';

    private $api;

    public function __construct(PurelymailApi $api)
    {
        $this->api = $api;
    }

    public static function onOurDomain($email)
    {
        $domain = strtolower(substr(strrchr((string) $email, '@'), 1));

        return in_array($domain, config('purelymail.domains'));
    }

    /**
     * Is this address a Purelymail mailbox (so its password lives on Purelymail)?
     * @return bool|null null when Purelymail can't be asked right now
     */
    public function backed($email)
    {
        $email = strtolower(trim((string) $email));
        if (!self::onOurDomain($email)) {
            return false;
        }
        try {
            $users = \Cache::remember(self::USERS_CACHE, 10, function () {
                return array_map('strtolower', $this->api->users());
            });
        } catch (\Exception $e) {
            \Helper::logException($e, '[Purelymail] listUser for sign-in');

            return null;
        }

        return in_array($email, $users);
    }

    public static function forgetUsers()
    {
        \Cache::forget(self::USERS_CACHE);
    }

    /**
     * IMAP LOGIN against Purelymail, using literals so any character in the password is safe.
     * @return bool|null true = accepted, false = rejected, null = could not check (network, server busy)
     */
    public function check($email, $password)
    {
        if ($password === '' || preg_match('/[\r\n\0]/', $email.$password)) {
            return false;
        }
        $imap = config('purelymail.imap');
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client('ssl://'.$imap['server'].':'.$imap['port'], $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            return null;
        }
        stream_set_timeout($fp, 15);
        try {
            if (strpos((string) fgets($fp), '* OK') !== 0) {
                return null;
            }
            $parts = [$email, $password];
            fwrite($fp, 'a1 LOGIN {'.strlen($parts[0])."}\r\n");
            foreach ($parts as $i => $part) {
                $line = $this->readUntilTagOrContinuation($fp);
                if ($line === null || $line[0] !== '+') {
                    return $this->verdict($line);
                }
                fwrite($fp, $part.($i === 0 ? ' {'.strlen($parts[1])."}\r\n" : "\r\n"));
            }
            $verdict = $this->verdict($this->readUntilTagOrContinuation($fp));
            if ($verdict) {
                fwrite($fp, "a2 LOGOUT\r\n");
            }

            return $verdict;
        } finally {
            fclose($fp);
        }
    }

    private function readUntilTagOrContinuation($fp)
    {
        while (($line = fgets($fp)) !== false) {
            if ($line[0] === '+' || strpos($line, 'a1 ') === 0) {
                return $line;
            }
        }

        return null;
    }

    private function verdict($line)
    {
        if ($line === null) {
            return null;
        }
        if (strpos($line, 'a1 OK') === 0) {
            return true;
        }
        // NO with a temporary-failure code means "try later", not "wrong password".
        if (strpos($line, 'a1 NO') === 0 && !preg_match('/\[(UNAVAILABLE|SERVERBUG|LIMIT|INUSE)\]/i', $line)) {
            return false;
        }

        return null;
    }

    /** Easy to read and type on a phone: Hq7k-m3Xp-Rt9w (about 70 bits). */
    public static function readablePassword()
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            do {
                $s = '';
                for ($i = 0; $i < 4; $i++) {
                    $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
            } while (!preg_match('/[0-9]/', $s) && $g === 0);
            $groups[] = $s;
        }

        return implode('-', $groups);
    }
}
