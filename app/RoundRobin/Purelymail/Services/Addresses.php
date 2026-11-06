<?php

namespace App\RoundRobin\Purelymail\Services;

use App\User;

/**
 * Sign-in is always the Round Robin address (Rahul, 2026-11-06): user.email must be on one of
 * config('purelymail.domains'). The personal email is only where the one-time setup mail goes
 * (Recovery, option purelymail.recovery.<id>); it is never a sign-in or a second identity.
 */
class Addresses
{
    public static function personal(User $user)
    {
        return Recovery::get($user);
    }

    public static function signInProblem($email)
    {
        if (!EmailPassword::onOurDomain($email)) {
            return __('The sign-in email must be a Round Robin address (:domains). The personal email goes in the field below, for the one-time setup mail.', ['domains' => implode(', ', config('purelymail.domains'))]);
        }

        return null;
    }
}
