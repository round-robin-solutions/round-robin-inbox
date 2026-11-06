<?php

namespace App\RoundRobin\Purelymail\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Support\Facades\Validator;
use App\RoundRobin\Purelymail\Services\Addresses;
use App\RoundRobin\Purelymail\Services\Recovery;

/**
 * Profile save: checks the two extra email fields before FreeScout saves anything, together with the
 * native required fields, so every problem shows at once against its field and the draft is kept.
 */
class ProfileEmails
{
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        if ($request->isMethod('get') && $route && $route->getName() === 'users.profile') {
            // Label FreeScout's own Email row (no hook for it; done on the rendered page).
            $response = $next($request);
            $html = method_exists($response, 'getContent') ? $response->getContent() : null;
            if (is_string($html)) {
                $label = '<label for="email" class="col-sm-2 control-label">'.__('Email').'</label>';
                $response->setContent(str_replace($label, '<label for="email" class="col-sm-2 control-label">'.e(__('Round Robin email (sign-in)')).'</label>', $html));
            }

            return $response;
        }
        if ($request->isMethod('post') && $request->is('users/wizard')) {
            return $this->newUser($request, $next);
        }
        if ($request->isMethod('post') && $route && $route->getName() === 'users.ajax' && $request->input('action') === 'send_invite') {
            return response()->json(['status' => 'error', 'msg' => __('Setup mails go through Profile » Login & password » Reset password (it sets the Round Robin mailbox password and emails it to the personal email). The native invite is turned off.')]);
        }
        if (!$request->isMethod('post') || !$route || $route->getName() !== 'users.profile.save') {
            return $next($request);
        }
        $user = User::find($route->parameter('id'));
        $me = auth()->user();
        if (!$user || !$me || !$me->can('update', $user)) {
            return $next($request); // the controller answers 403/404
        }

        $rules = [
            'personal_email' => ['nullable', 'email', 'max:100'],
        ];
        $messages = [
            'personal_email.email' => __('Enter a valid personal email, like name@gmail.com.'),
        ];
        // The native rules that fail most often, so they are reported in the same pass.
        $rules += [
            'first_name' => 'required|string|max:20',
            'last_name'  => 'max:30',
            'email'      => $me->isAdmin() ? 'required|string|email|max:100|unique:users,email,'.$user->id : 'nullable',
            'timezone'   => 'required|string|max:255',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        $validator->after(function ($v) use ($request, $me) {
            if (!$v->errors()->has('personal_email') && ($p = Recovery::problem(trim((string) $request->input('personal_email'))))) {
                $v->errors()->add('personal_email', $p);
            }
            if ($me->isAdmin() && !$v->errors()->has('email') && ($p = Addresses::signInProblem(trim((string) $request->input('email'))))) {
                $v->errors()->add('email', $p);
            }
        });
        if ($validator->fails()) {
            return redirect()->route('users.profile', ['id' => $user->id])->withErrors($validator)->withInput();
        }

        return $next($request);
    }

    /** New User: sign-in must be a Round Robin address; the native invite is not used (see the message). */
    private function newUser($request, Closure $next)
    {
        $validator = Validator::make($request->all(), []);
        if ($p = Addresses::signInProblem(trim((string) $request->input('email')))) {
            $validator->errors()->add('email', $p);
        }
        if ($request->input('send_invite')) {
            $validator->errors()->add('email', __('Untick "Send an invitation email". To set a person up, use Manage » Create inbox (Purelymail) » Person: it creates the Round Robin mailbox and emails the login details to their personal email.'));
        }
        if ($validator->errors()->any()) {
            // Native required fields are reported in the same pass.
            $native = Validator::make($request->all(), ['first_name' => 'required|string|max:20', 'last_name' => 'max:30', 'email' => 'required|string|email|max:100|unique:users']);
            foreach ($native->errors()->messages() as $k => $m) {
                if (!$validator->errors()->has($k)) {
                    $validator->errors()->add($k, $m[0]);
                }
            }

            return redirect()->route('users.create')->withErrors($validator)->withInput();
        }

        return $next($request);
    }
}
