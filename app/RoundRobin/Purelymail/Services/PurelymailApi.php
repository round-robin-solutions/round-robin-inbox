<?php

namespace App\RoundRobin\Purelymail\Services;

/**
 * Minimal Purelymail API client (https://news.purelymail.com/api/index.html).
 * Every call is a POST to /api/v0/<endpoint> with a JSON body and the Purelymail-Api-Token header.
 */
class PurelymailApi
{
    private $token;

    public function __construct()
    {
        $file = config('purelymail.token_file');
        $this->token = is_readable($file) ? trim(file_get_contents($file)) : '';
    }

    public function configured()
    {
        return $this->token !== '';
    }

    public function call($endpoint, array $body = [])
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Purelymail API token is missing ('.config('purelymail.token_file').').');
        }
        $ch = curl_init('https://purelymail.com/api/v0/'.$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Purelymail-Api-Token: '.$this->token],
            CURLOPT_POSTFIELDS     => json_encode($body ?: new \stdClass()),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        if ($raw === false) {
            throw new \RuntimeException('Purelymail API unreachable: '.$err);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'success') {
            $msg = trim(($data['code'] ?? '').' '.($data['message'] ?? '')) ?: substr((string) $raw, 0, 200);
            throw new \RuntimeException('Purelymail '.$endpoint.' failed: '.$msg);
        }

        return $data['result'] ?? [];
    }

    public function users()
    {
        return $this->call('listUser')['users'] ?? [];
    }

    /** $recovery_email: personal address for Purelymail's "lost password" page (people only). */
    public function createUser($local, $domain, $password, $recovery_email = null)
    {
        $body = [
            'userName'             => $local,
            'domainName'           => $domain,
            'password'             => $password,
            'enablePasswordReset'  => (bool) $recovery_email,
            'enableSearchIndexing' => true,
            'sendWelcomeEmail'     => false,
        ];
        if ($recovery_email) {
            $body['recoveryEmail'] = $recovery_email;
            $body['recoveryEmailDescription'] = 'Personal email';
        }

        return $this->call('createUser', $body);
    }

    /** Only used to roll back a user this module created a moment earlier. */
    public function deleteUser($address)
    {
        return $this->call('deleteUser', ['userName' => $address]);
    }

    public function setPassword($address, $password)
    {
        return $this->call('modifyUser', ['userName' => $address, 'newPassword' => $password]);
    }

    public function routingRules()
    {
        return $this->call('listRoutingRules')['rules'] ?? [];
    }

    /** Routing rule: <match>@<domain> (or any local part starting with <match> when $prefix) -> $target. */
    public function addRule($match, $prefix, $domain, $target)
    {
        return $this->call('createRoutingRule', [
            'domainName'      => $domain,
            'prefix'          => (bool) $prefix,
            'matchUser'       => $match,
            'targetAddresses' => [$target],
            'catchall'        => false,
        ]);
    }

    public function appPassword($address, $name)
    {
        return $this->call('createAppPassword', ['userHandle' => $address, 'name' => $name])['appPassword'];
    }
}
