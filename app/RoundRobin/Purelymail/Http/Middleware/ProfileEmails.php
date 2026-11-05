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
            // Label FreeScout's own Email row "Sign-in email" (no hook for it; done on the rendered page).
            $response = $next($request);
            $html = method_exists($response, 'getContent') ? $response->getContent() : null;
            if (is_string($html)) {
                $label = '<label for="email" class="col-sm-2 control-label">'.__('Email').'</label>';
                $response->setContent(str_replace($label, '<label for="email" class="col-sm-2 control-label">'.e(__('Sign-in email')).'</label>', $html));
            }

            return $response;
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
            'rr_email.email'       => __('Enter a valid Round Robin email, like name@roundrobin.in.'),
        ];
        $editsWork = $me->isAdmin() && $request->has('rr_email') && !Addresses::workIsSignIn($user);
        if ($editsWork) {
            $rules['rr_email'] = ['nullable', 'email', 'max:100'];
        }
        // The native rules that fail most often, so they are reported in the same pass.
        $rules += [
            'first_name' => 'required|string|max:20',
            'last_name'  => 'max:30',
            'email'      => $me->isAdmin() ? 'required|string|email|max:100|unique:users,email,'.$user->id : 'nullable',
            'timezone'   => 'required|string|max:255',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        $validator->after(function ($v) use ($request, $editsWork) {
            if (!$v->errors()->has('personal_email') && ($p = Recovery::problem(trim((string) $request->input('personal_email'))))) {
                $v->errors()->add('personal_email', $p);
            }
            if ($editsWork && !$v->errors()->has('rr_email') && ($p = Addresses::workProblem(trim((string) $request->input('rr_email'))))) {
                $v->errors()->add('rr_email', $p);
            }
        });
        if ($validator->fails()) {
            return redirect()->route('users.profile', ['id' => $user->id])->withErrors($validator)->withInput();
        }

        return $next($request);
    }
}
