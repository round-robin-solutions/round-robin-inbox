<?php

namespace App\RoundRobin\Purelymail\Http\Controllers;

use App\Mailbox;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\RoundRobin\Purelymail\Mail\LoginDetails;
use App\RoundRobin\Purelymail\Services\EmailPassword;
use App\RoundRobin\Purelymail\Services\PurelymailApi;
use App\RoundRobin\Purelymail\Services\Provisioner;

class PurelymailController extends Controller
{
    public function index(PurelymailApi $api)
    {
        $error = null;
        $unconnected = [];
        try {
            $connected = array_map('strtolower', Mailbox::pluck('email')->all());
            foreach ($api->users() as $address) {
                $address = strtolower($address);
                $domain = substr(strrchr($address, '@'), 1);
                if (in_array($domain, config('purelymail.domains')) && !in_array($address, $connected)
                    && !in_array($address, config('purelymail.personal'))) {
                    $unconnected[] = $address;
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $unconfigured = Mailbox::whereNull('in_server')->get()->filter(function ($m) {
            return Provisioner::eligible($m);
        });

        return view('purelymail::index', [
            'unconfigured' => $unconfigured,
            'domains'     => config('purelymail.domains'),
            'users'       => User::where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get(),
            'mailboxes'   => Mailbox::orderBy('name')->get(),
            'unconnected' => $unconnected,
            'api_error'   => $error,
        ]);
    }

    /**
     * One form, two kinds of address:
     * - shared: new Purelymail user + app password + FreeScout mailbox (careers@, support@, ...);
     * - person: Purelymail user (unless it exists) + FreeScout sign-in on the same address, one password,
     *   login details emailed to their personal address. FreeScout forbids a user and a mailbox sharing
     *   an address, so a person's own mail stays in webmail and phone apps (Rahul, 2026-11-03).
     */
    public function create(Request $request, PurelymailApi $api)
    {
        $person = $request->input('kind') === 'person';
        $rules = [
            'kind'   => ['required', 'in:shared,person'],
            'local'  => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/'],
            'domain' => ['required', 'in:'.implode(',', config('purelymail.domains'))],
        ];
        if ($person) {
            $rules += [
                'first_name'  => ['required', 'string', 'max:20'],
                'last_name'   => ['nullable', 'string', 'max:30'],
                'role'        => ['required', 'in:user,admin'],
                'mailboxes'   => ['array'],
                'mailboxes.*' => ['integer'],
                'send_to'     => ['nullable', 'email', 'max:100'],
            ];
        } else {
            $rules += ['name' => ['required', 'string', 'max:40'], 'users' => ['array'], 'users.*' => ['integer']];
        }
        $validator = \Validator::make($request->all(), $rules, [
            'local.regex'         => __('Use lowercase letters, digits, dots, dashes or underscores.'),
            'first_name.required' => __('Enter their first name.'),
            'send_to.email'       => __('Enter a valid personal email, like name@gmail.com.'),
        ]);
        $address = strtolower($request->input('local').'@'.$request->input('domain'));
        $validator->after(function ($v) use ($address, $person, $request) {
            if (!$v->errors()->has('local') && !$v->errors()->has('domain')
                && ($problem = $person ? $this->personProblems($address) : $this->addressProblems($address))) {
                $v->errors()->add('local', $problem);
            }
            if ($person && strtolower(trim((string) $request->input('send_to'))) === $address) {
                $v->errors()->add('send_to', __('Use their personal email: they can only read :address once they have the password.', ['address' => $address]));
            }
        });
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $data = $validator->getData();

        if ($person) {
            try {
                $result = $this->createPerson($api, $address, $data);
            } catch (\Exception $e) {
                \Helper::logException($e, '[Purelymail] create person '.$address);

                return back()->withErrors(['local' => $e->getMessage()])->withInput();
            }
            \Session::flash('purelymail_person', $result);

            return redirect()->route('purelymail.index');
        }

        $password = Provisioner::randomPassword();
        try {
            $api->createUser($data['local'], $data['domain'], $password);
            (new Provisioner($api))->gmailStyleAlias($address);
            $mailbox = $this->connectMailbox($api, $address, $data['name'], $data['users'] ?? []);
        } catch (\Exception $e) {
            \Helper::logException($e, '[Purelymail] create '.$address);

            return back()->withErrors(['local' => $e->getMessage()])->withInput();
        }
        EmailPassword::forgetUsers();

        \Session::flash('purelymail_created', [
            'address'  => $address,
            'password' => $password,
            'mailbox'  => route('mailboxes.view', ['id' => $mailbox->id]),
        ]);

        return redirect()->route('purelymail.index');
    }

    private function createPerson(PurelymailApi $api, $address, array $data)
    {
        [$local, $domain] = explode('@', $address, 2);
        $send_to = !empty($data['send_to']) ? strtolower(trim($data['send_to'])) : null;
        $existing = in_array($address, array_map('strtolower', $api->users()));
        // An existing mailbox keeps its password (their phone already uses it); a new one gets a typeable one.
        $password = $existing ? null : EmailPassword::readablePassword();
        if (!$existing) {
            $api->createUser($local, $domain, $password, $send_to);
        }

        $user = null;
        try {
            if (!$existing) {
                (new Provisioner($api))->gmailStyleAlias($address);
            }
            $user = User::create([
                'first_name' => trim(strip_tags($data['first_name'])),
                'last_name'  => trim(strip_tags($data['last_name'] ?? '')),
                'email'      => $address,
                // Existing mailbox: a throwaway hash; the first sign-in with the email password replaces it.
                'password'   => $password ?: EmailPassword::readablePassword().EmailPassword::readablePassword(),
            ]);
            $user->role = $data['role'] === 'admin' ? User::ROLE_ADMIN : User::ROLE_USER;
            $user->status = User::STATUS_ACTIVE;
            $user->invite_state = User::INVITE_STATE_ACTIVATED;
            $user->timezone = config('app.timezone') ?: User::DEFAULT_TIMEZONE;
            $user->save();
            $ids = Mailbox::whereIn('id', array_map('intval', $data['mailboxes'] ?? []))->pluck('id')->all();
            $user->mailboxes()->sync($ids);
            $user->syncPersonalFolders($ids);
            if ($send_to) { // "Forgot password" sends the link here (Services/Recovery)
                \App\Option::set('purelymail.recovery.'.$user->id, $send_to);
            }
        } catch (\Exception $e) {
            if ($user) {
                // FreeScout's own removal (status deleted, address freed); a hard delete trips its UserObserver.
                $user->deleteUser(auth()->user(), []);
            }
            if (!$existing) {
                try {
                    $api->deleteUser($address);
                } catch (\Exception $e2) {
                    \Helper::logException($e2, '[Purelymail] roll back '.$address);
                }
            }
            throw $e;
        }
        EmailPassword::forgetUsers();

        $mailbox_names = Mailbox::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
        $emailed = null;
        $mail_error = null;
        if ($send_to) {
            try {
                \App\Misc\Mail::setSystemMailDriver();
                \Mail::to($send_to)->send(new LoginDetails([
                    'first_name'    => $user->first_name,
                    'address'       => $address,
                    'password'      => $password,
                    'mailboxes'     => $mailbox_names,
                    'dashboard'     => rtrim(config('app.url'), '/').'/',
                    'webmail'       => config('purelymail.webmail'),
                    'lost_password' => $existing ? null : config('purelymail.lost_password'),
                    'imap'          => config('purelymail.imap')['server'].':'.config('purelymail.imap')['port'],
                    'smtp'          => config('purelymail.smtp')['server'].':'.config('purelymail.smtp')['port'],
                ]));
                if (count(\Mail::failures())) {
                    throw new \RuntimeException(__('the mail server refused :to', ['to' => $send_to]));
                }
                $emailed = $send_to;
            } catch (\Exception $e) {
                \Helper::logException($e, '[Purelymail] login details to '.$send_to);
                $mail_error = $e->getMessage();
            }
        }

        return [
            'name'       => $user->getFullName(),
            'address'    => $address,
            'password'   => $password,
            'existing'   => $existing,
            'mailboxes'  => $mailbox_names,
            'emailed'    => $emailed,
            'send_to'    => $send_to,
            'mail_error' => $mail_error,
            'user_url'   => route('users.profile', ['id' => $user->id]),
        ];
    }

    /** Existing Purelymail user -> app password + FreeScout mailbox. */
    public function connect(Request $request, PurelymailApi $api)
    {
        $data = $request->validate(['address' => ['required', 'email'], 'name' => ['required', 'string', 'max:40']]);
        $address = strtolower($data['address']);
        try {
            if (!in_array($address, array_map('strtolower', $api->users()))) {
                throw new \RuntimeException(__('No Purelymail inbox :address.', ['address' => $address]));
            }
            if ($problems = $this->addressProblems($address)) {
                throw new \RuntimeException($problems);
            }
            $mailbox = $this->connectMailbox($api, $address, $data['name'], []);
        } catch (\Exception $e) {
            return back()->withErrors(['connect' => $e->getMessage()]);
        }
        \Session::flash('flash_success_floating', __('Connected :address', ['address' => $address]));

        return redirect()->route('mailboxes.view', ['id' => $mailbox->id]);
    }

    private function personProblems($address)
    {
        if (Mailbox::where('email', $address)->exists()) {
            return __(':address is a shared inbox here. A person cannot sign in with the same address as a mailbox.', ['address' => $address]);
        }
        if (Mailbox::userEmailExists($address)) {
            return __(':address already has a sign-in.', ['address' => $address]);
        }

        return null;
    }

    private function addressProblems($address)
    {
        if (Mailbox::where('email', $address)->exists()) {
            return __('A mailbox for :address already exists.', ['address' => $address]);
        }
        if (Mailbox::userEmailExists($address)) {
            return __('A FreeScout user has this address; users and mailboxes cannot share one.');
        }

        return null;
    }

    private function connectMailbox(PurelymailApi $api, $address, $name, array $user_ids)
    {
        $mailbox = new Mailbox();
        $mailbox->fill(['email' => $address, 'name' => trim(strip_tags($name))]);
        $mailbox->in_server = 'pending'; // keeps the created-hook from provisioning twice
        $mailbox->save();
        try {
            (new Provisioner($api))->configure($mailbox);
        } catch (\Exception $e) {
            $mailbox->delete();
            throw $e;
        }

        $user_ids = array_map('intval', $user_ids);
        $mailbox->users()->sync($user_ids);
        $mailbox->syncPersonalFolders($user_ids);

        return $mailbox;
    }

    /** Mailbox made with FreeScout's own "New Mailbox" -> Purelymail user (if missing) + settings. */
    public function setup(Request $request, PurelymailApi $api)
    {
        $mailbox = Mailbox::findOrFail((int) $request->mailbox_id);
        if (!Provisioner::eligible($mailbox)) {
            return back()->withErrors(['connect' => __(':address is already set up.', ['address' => $mailbox->email])]);
        }
        try {
            $password = (new Provisioner($api))->provision($mailbox);
        } catch (\Exception $e) {
            return back()->withErrors(['connect' => $e->getMessage()]);
        }
        \Session::flash('purelymail_created', [
            'address'  => $mailbox->email,
            'password' => $password,
            'mailbox'  => route('mailboxes.view', ['id' => $mailbox->id]),
        ]);

        return redirect()->route('purelymail.index');
    }
}
