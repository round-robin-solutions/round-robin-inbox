<?php

namespace App\RoundRobin\Purelymail\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use App\RoundRobin\Purelymail\Mail\NewPassword;
use App\RoundRobin\Purelymail\Services\EmailPassword;
use App\RoundRobin\Purelymail\Services\PurelymailApi;
use App\RoundRobin\Purelymail\Services\Recovery;

/**
 * Profile » Login & password (Rahul, 2026-11-03: "no option to reset password or send password").
 * - Everyone: their personal email for "Forgot password".
 * - Admins, for someone else: set a new password (Purelymail too, when the address is a Purelymail
 *   mailbox), shown once, and optionally emailed to the person's personal address.
 */
class LoginController extends Controller
{
    private function target($id)
    {
        $user = User::findOrFail($id);
        $me = auth()->user();
        if (!$me->isAdmin() && $me->id !== $user->id) {
            abort(403);
        }

        return $user;
    }

    public function show($id)
    {
        $user = $this->target($id);
        $backed = app(EmailPassword::class)->backed($user->email);

        return view('purelymail::login', [
            'user'     => $user,
            'users'    => User::where('status', '!=', User::STATUS_DELETED)->get()->except($id),
            'recovery' => Recovery::get($user),
            'backed'   => $backed,
            'is_self'  => auth()->id() === $user->id,
        ]);
    }

    public function recovery($id, Request $request)
    {
        $user = $this->target($id);
        $data = $request->validate(['recovery' => ['nullable', 'email', 'max:100']],
            ['recovery.email' => __('Enter a valid personal email, like name@gmail.com.')]);
        $email = trim((string) ($data['recovery'] ?? ''));
        if ($problem = Recovery::problem($email)) {
            return back()->withErrors(['recovery' => $problem])->withInput();
        }
        try {
            app(Recovery::class)->set($user, $email ?: null);
        } catch (\Exception $e) {
            \Helper::logException($e, '[Purelymail] recovery email '.$user->email);

            return back()->withErrors(['recovery' => $e->getMessage()])->withInput();
        }
        \Session::flash('flash_success_floating', $email ? __('Personal email saved.') : __('Personal email removed.'));

        return redirect()->route('purelymail.login', ['id' => $user->id]);
    }

    public function reset($id, Request $request)
    {
        $user = $this->target($id);
        if (!auth()->user()->isAdmin() || auth()->id() === $user->id) {
            abort(403); // your own password: Profile » Change your password
        }
        $data = $request->validate([
            'send_to' => ['nullable', 'email', 'max:100'],
        ], ['send_to.email' => __('Enter a valid personal email, like name@gmail.com.')]);
        $send_to = strtolower(trim((string) ($data['send_to'] ?? '')));
        if ($send_to && strtolower($user->email) === $send_to) {
            return back()->withErrors(['send_to' => __('Send it to their personal email: they can only read :address once they have the password.', ['address' => $user->email])])->withInput();
        }

        $backed = app(EmailPassword::class)->backed($user->email);
        if ($backed === null) {
            return back()->withErrors(['send_to' => __('Purelymail is not answering, so nothing was changed. Try again in a minute.')])->withInput();
        }
        $password = EmailPassword::readablePassword();
        try {
            if ($backed) {
                app(PurelymailApi::class)->setPassword(strtolower($user->email), $password);
            }
        } catch (\Exception $e) {
            \Helper::logException($e, '[Purelymail] admin reset '.$user->email);

            return back()->withErrors(['send_to' => __('The email password could not be changed, so nothing was changed: :error', ['error' => $e->getMessage()])])->withInput();
        }
        $user->password = Hash::make($password);
        $user->invite_state = User::INVITE_STATE_ACTIVATED; // a pending invite is replaced by this password
        $user->setRememberToken(\Illuminate\Support\Str::random(60)); // "remember me" cookies stop working
        $user->save();

        if ($send_to && !Recovery::get($user) && !Recovery::problem($send_to)) {
            try {
                app(Recovery::class)->set($user, $send_to);
            } catch (\Exception $e) {
                \Helper::logException($e, '[Purelymail] recovery email on reset '.$user->email);
            }
        }

        $emailed = null;
        $mail_error = null;
        if ($send_to) {
            try {
                \App\Misc\Mail::setSystemMailDriver();
                \Mail::to($send_to)->send(new NewPassword([
                    'first_name' => $user->first_name,
                    'address'    => $user->email,
                    'password'   => $password,
                    'backed'     => (bool) $backed,
                    'by'         => auth()->user()->getFullName(),
                    'dashboard'  => rtrim(config('app.url'), '/').'/',
                    'webmail'    => config('purelymail.webmail'),
                ]));
                if (count(\Mail::failures())) {
                    throw new \RuntimeException(__('the mail server refused :to', ['to' => $send_to]));
                }
                $emailed = $send_to;
            } catch (\Exception $e) {
                \Helper::logException($e, '[Purelymail] new password to '.$send_to);
                $mail_error = $e->getMessage();
            }
        }

        \Session::flash('purelymail_reset', [
            'password'   => $password,
            'backed'     => (bool) $backed,
            'emailed'    => $emailed,
            'send_to'    => $send_to,
            'mail_error' => $mail_error,
        ]);

        return redirect()->route('purelymail.login', ['id' => $user->id]);
    }
}
