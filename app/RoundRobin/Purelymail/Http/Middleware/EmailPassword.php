<?php

namespace App\RoundRobin\Purelymail\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Hash;
use App\RoundRobin\Purelymail\Services\EmailPassword as Passwords;
use App\RoundRobin\Purelymail\Mail\ResetLink;
use App\RoundRobin\Purelymail\Services\PurelymailApi;
use App\RoundRobin\Purelymail\Services\Recovery;

/**
 * Keeps the dashboard password equal to the Purelymail email password (pushed onto the "web" group).
 *
 * - Sign-in: the typed password is checked with Purelymail first. Accepted: the FreeScout hash is
 *   refreshed so FreeScout's own login succeeds. Rejected while it still matches the cached hash
 *   (an old password, changed in webmail since): the cache is scrambled so the old one stops working.
 *   Purelymail unreachable: FreeScout checks the cached hash as usual.
 * - Change password (Profile): Purelymail is updated first, then FreeScout saves its copy.
 * - Forgot password: the link goes to the person's personal email when one is set (Services/Recovery).
 * - Forgot-password reset: after FreeScout saves the new password, Purelymail gets it too; if that
 *   fails the old hash is put back.
 * Addresses that are not Purelymail mailboxes (info@ is Google) are untouched.
 */
class EmailPassword
{
    // Purelymail must never see a flood of failed logins from this server: it also fetches every inbox.
    const PER_ADDRESS = 5;
    const ALL_ADDRESSES = 30;
    const WINDOW_MINUTES = 15;

    public function handle($request, Closure $next)
    {
        if (!$request->isMethod('post')) {
            return $next($request);
        }
        $route = $request->route();
        $name = $route ? $route->getName() : null;

        if ($name === 'users.password.save') {
            return $this->changePassword($request, $next);
        }
        if ($request->is('password/email')) {
            return $this->forgotPassword($request, $next);
        }
        if ($request->is('password/reset')) { // Laravel 5.5 leaves this POST route unnamed
            return $this->resetPassword($request, $next);
        }
        if ($request->is(trim(config('app.login_path'), '/'))) {
            $this->signIn($request);
        }

        return $next($request);
    }

    private function signIn($request)
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $user = $email !== '' ? User::where('email', $email)->first() : null;
        if (!$user || $password === '') {
            return;
        }
        $passwords = app(Passwords::class);
        if ($passwords->backed($email) !== true) {
            return;
        }
        $limiter = app(RateLimiter::class);
        $keys = ['purelymail-login|'.$email => self::PER_ADDRESS, 'purelymail-login|*' => self::ALL_ADDRESSES];
        foreach ($keys as $key => $max) {
            if ($limiter->tooManyAttempts($key, $max)) {
                return; // FreeScout's own throttle and the cached hash take over
            }
        }

        $ok = $passwords->check($email, $password);
        if ($ok === true) {
            if (!Hash::check($password, $user->password)) {
                $user->password = Hash::make($password);
                $user->save();
            }

            return;
        }
        if ($ok === false) {
            foreach ($keys as $key => $max) {
                $limiter->hit($key, self::WINDOW_MINUTES);
            }
            if (Hash::check($password, $user->password)) {
                $user->password = Hash::make(Passwords::readablePassword().Passwords::readablePassword());
                $user->save();
            }
        }
    }

    private function changePassword($request, Closure $next)
    {
        $user = auth()->user();
        if (!$user || (int) $request->route('id') !== (int) $user->id) {
            return $next($request); // FreeScout answers 403 for someone else's password
        }
        $new = (string) $request->input('password');
        $backed = app(Passwords::class)->backed($user->email);
        if ($backed === false) {
            return $next($request);
        }
        // Same checks FreeScout makes; if any fails, FreeScout shows its own errors.
        $valid = Hash::check((string) $request->input('password_current'), $user->password)
            && strlen($new) >= 8 && $new === (string) $request->input('password_confirmation')
            && !Hash::check($new, $user->password);
        if (!$valid) {
            return $next($request);
        }
        try {
            if ($backed === null) {
                throw new \RuntimeException(__('Purelymail is not answering.'));
            }
            app(PurelymailApi::class)->setPassword(strtolower($user->email), $new);
        } catch (\Exception $e) {
            \Helper::logException($e, '[Purelymail] change password '.$user->email);

            return redirect()->route('users.password', ['id' => $user->id])
                ->withErrors(['password' => __('Your email password could not be changed, so nothing was changed. Try again in a minute.')]);
        }

        $response = $next($request);
        \Session::flash('flash_success_floating', __('Password changed. Use the new one for webmail and mail apps on your phone too.'));

        return $response;
    }

    /**
     * "Forgot password": when the person has a personal email (Profile » Login & password), the link goes
     * there instead of the work address, which can't be read without the password. Same answer either way.
     */
    private function forgotPassword($request, Closure $next)
    {
        $email = strtolower(trim((string) $request->input('email')));
        $user = $email !== '' ? User::where('email', $email)->where('status', User::STATUS_ACTIVE)->first() : null;
        $personal = $user ? Recovery::get($user) : null;
        if (!$personal) {
            return $next($request); // FreeScout's own flow: link to the work address
        }
        $limiter = app(RateLimiter::class);
        $key = 'purelymail-forgot|'.$email;
        if (!$limiter->tooManyAttempts($key, 3)) {
            $limiter->hit($key, self::WINDOW_MINUTES);
            try {
                $token = \Password::broker()->createToken($user);
                \App\Misc\Mail::setSystemMailDriver();
                \Mail::to($personal)->send(new ResetLink([
                    'first_name' => $user->first_name,
                    'address'    => $user->email,
                    'url'        => route('password.reset', ['token' => $token]),
                    'backed'     => app(Passwords::class)->backed($email) === true,
                ]));
            } catch (\Exception $e) {
                \Helper::logException($e, '[Purelymail] forgot password '.$email);
            }
        }

        return back()->with('status', __('If an account exists for this email, you will receive a password reset link.'));
    }

    private function resetPassword($request, Closure $next)
    {
        $email = strtolower(trim((string) $request->input('email')));
        $user = $email !== '' ? User::where('email', $email)->first() : null;
        if (!$user || app(Passwords::class)->backed($email) === false) {
            return $next($request);
        }
        $old = $user->password;
        $new = (string) $request->input('password');

        $response = $next($request);

        $fresh = User::find($user->id);
        if (!$fresh || $fresh->password === $old || !Hash::check($new, $fresh->password)) {
            return $response;
        }
        try {
            app(PurelymailApi::class)->setPassword($email, $new);
        } catch (\Exception $e) {
            \Helper::logException($e, '[Purelymail] reset password '.$email);
            $fresh->password = $old;
            $fresh->save();

            return redirect()->route('login')->withErrors(['email' => __('Your email password could not be changed, so your old password still works. Try the reset again in a minute.')]);
        }

        return $response;
    }
}
