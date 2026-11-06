<?php

namespace App\RoundRobin\Purelymail\Services;

use App\Option;
use App\User;

/**
 * Personal (recovery) email per person (Rahul, 2026-11-03: "reset password or send password").
 *
 * A forgotten password can't be recovered through the work address, because reading that mail needs
 * the same password. So "Forgot password" and admin resets send to a personal address instead.
 * Stored in FreeScout options as purelymail.recovery.<user id>. For Purelymail mailboxes it is also
 * set as the Purelymail reset method, so purelymail.com/user/lostPassword works for webmail too.
 */
class Recovery
{
    private $api;

    public function __construct(PurelymailApi $api)
    {
        $this->api = $api;
    }

    public static function get(User $user)
    {
        $email = Option::get('purelymail.recovery.'.$user->id, '', false, false);

        return $email ? strtolower($email) : null;
    }

    /** Saves the address here and, for Purelymail mailboxes, as the Purelymail reset method. */
    public function set(User $user, $email)
    {
        $email = $email ? strtolower(trim($email)) : null;
        $old = self::get($user);
        $address = strtolower($user->email);
        if (app(EmailPassword::class)->backed($address) === true) {
            // Replace, not edit: upsert with existingTarget answers "Reset method not found" (2026-11-03).
            if ($old && $old !== $email && $this->hasMethod($address, $old)) {
                $this->api->call('deletePasswordReset', ['userName' => $address, 'target' => $old]);
            }
            if ($email) {
                $this->api->call('upsertPasswordReset', ['userName' => $address, 'type' => 'email', 'target' => $email,
                    'description' => 'Personal email', 'allowMfaReset' => false]);
                $this->api->call('modifyUser', ['userName' => $address, 'enablePasswordReset' => true]);
            }
        }
        if ($email) {
            Option::set('purelymail.recovery.'.$user->id, $email);
        } else {
            Option::remove('purelymail.recovery.'.$user->id);
        }
    }

    private function hasMethod($address, $target)
    {
        $methods = $this->api->call('listPasswordReset', ['userName' => $address])['users'] ?? [];
        foreach ($methods as $m) {
            if (strtolower($m['target'] ?? '') === strtolower($target)) {
                return true;
            }
        }

        return false;
    }

    /** A personal address must not be on our own domains: reading it would need the lost password. */
    public static function problem($email)
    {
        if ($email && EmailPassword::onOurDomain($email)) {
            return __('Use a personal address (Gmail, Outlook…), not a :domain one: if the password is lost, that mailbox is locked too.', ['domain' => substr(strrchr($email, '@'), 1)]);
        }

        return null;
    }
}
