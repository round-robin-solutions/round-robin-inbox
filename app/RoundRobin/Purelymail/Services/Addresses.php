<?php

namespace App\RoundRobin\Purelymail\Services;

use App\Option;
use App\User;

/**
 * The two addresses shown on Profile (Rahul, 2026-11-06: "Show both emails here").
 * - Round Robin email: the FreeScout email when it is on our domains, else the stored purelymail.address.<id>.
 * - Personal email: the Recovery value; while none is stored and the FreeScout email is not ours, that email.
 */
class Addresses
{
    public static function work(User $user)
    {
        if (EmailPassword::onOurDomain($user->email)) {
            return strtolower($user->email);
        }
        $stored = Option::get('purelymail.address.'.$user->id, '', false, false);

        return $stored ? strtolower($stored) : null;
    }

    /** Whether the Round Robin email is just the sign-in email (edited there, not here). */
    public static function workIsSignIn(User $user)
    {
        return EmailPassword::onOurDomain($user->email);
    }

    public static function personal(User $user)
    {
        if ($recovery = Recovery::get($user)) {
            return $recovery;
        }

        return $user->email && !EmailPassword::onOurDomain($user->email) ? strtolower($user->email) : null;
    }

    public static function setWork(User $user, $email)
    {
        if ($email) {
            Option::set('purelymail.address.'.$user->id, strtolower(trim($email)));
        } else {
            Option::remove('purelymail.address.'.$user->id);
        }
    }

    public static function workProblem($email)
    {
        if ($email && !EmailPassword::onOurDomain($email)) {
            return __('Use an address on :domains.', ['domains' => implode(', ', config('purelymail.domains'))]);
        }

        return null;
    }
}
