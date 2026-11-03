@extends('layouts.app')

@section('title', __('Create inbox'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default panel-wizard">
                <div class="panel-body">
                    <div class="wizard-header">
                        <h1>{{ __('Create an inbox') }}</h1>
                        <p>{{ __('Creates the address on Purelymail and connects it here, ready to receive and reply.') }}</p>
                    </div>
                    <div class="wizard-body">
                        @include('partials/flash_messages')

                        @if ($errors->has('connect'))
                            <div class="alert alert-danger">{{ $errors->first('connect') }}</div>
                        @endif
                        @if ($api_error)
                            <div class="alert alert-danger">{{ $api_error }}</div>
                        @endif

                        @if ($created = session('purelymail_created'))
                            <div class="alert alert-success">
                                <p><strong>{{ $created['address'] }}</strong> {{ __('is ready.') }}
                                    <a href="{{ $created['mailbox'] }}">{{ __('Open the mailbox') }}</a></p>
                                @if ($created['password'])
                                <p>{{ __('Webmail password (inbox.purelymail.com). It is shown once; save it in the team password manager now:') }}</p>
                                <p><code style="font-size:15px;user-select:all">{{ $created['password'] }}</code></p>
                                @endif
                                <p class="text-help">{{ __('This dashboard uses its own app password, so you only need the one above for webmail or a phone.') }}</p>
                            </div>
                        @endif

                        @if ($p = session('purelymail_person'))
                            <div class="alert alert-success" id="pm-person-result">
                                <p><strong>{{ $p['name'] }}</strong> {{ __('can now sign in as') }} <strong>{{ $p['address'] }}</strong>.
                                    <a href="{{ $p['user_url'] }}">{{ __('Open their profile') }}</a></p>
                                @if ($p['emailed'])
                                    <p>{{ __('Login details were emailed to :to.', ['to' => $p['emailed']]) }}</p>
                                @elseif ($p['mail_error'])
                                    <p class="text-danger"><strong>{{ __('The email to :to did not go out (:error). Send them the details below yourself.', ['to' => $p['send_to'], 'error' => $p['mail_error']]) }}</strong></p>
                                @else
                                    <p>{{ __('No personal email was given, so send them the details below yourself.') }}</p>
                                @endif
                                @if ($p['password'])
                                    <p>{{ __('Password (one password for webmail, phone and this dashboard; shown once):') }}
                                        <code style="font-size:15px;user-select:all">{{ $p['password'] }}</code></p>
                                @else
                                    <p>{{ __('This address already had a mailbox, so they sign in with their current email password.') }}</p>
                                @endif
                                @if (count($p['mailboxes']))
                                    <p class="text-help">{{ __('They can see: :list.', ['list' => implode(', ', $p['mailboxes'])]) }}</p>
                                @endif
                            </div>
                        @endif

                        <form class="form-horizontal margin-top" method="POST" action="{{ route('purelymail.create') }}" novalidate id="pm-form">
                            {{ csrf_field() }}
                            @php $kind = old('kind', 'shared'); @endphp

                            <div class="alert alert-danger" role="alert" tabindex="-1" id="pm-errors"
                                 @if (!count(array_diff($errors->keys(), ['connect']))) hidden @endif>
                                <p>{{ __('Please fix these and submit again:') }}</p>
                                <ul id="pm-error-list">
                                    @foreach (array_diff($errors->keys(), ['connect']) as $field)
                                        <li><a href="#pm-{{ str_replace('_', '-', explode('.', $field)[0]) }}">{{ $errors->first($field) }}</a></li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-4 control-label" id="pm-kind">{{ __('Who is it for?') }}</label>
                                <div class="col-sm-6">
                                    <div class="radio"><label>
                                        <input type="radio" name="kind" value="shared" @if ($kind !== 'person') checked @endif>
                                        {{ __('A shared inbox the team answers') }} <span class="text-help">careers@, support@</span>
                                    </label></div>
                                    <div class="radio"><label>
                                        <input type="radio" name="kind" value="person" @if ($kind === 'person') checked @endif>
                                        {{ __('A person: their own address and a sign-in here') }} <span class="text-help">first.last@</span>
                                    </label></div>
                                </div>
                            </div>

                            <div data-kind="person" @if ($kind !== 'person') hidden @endif>
                                <div class="form-group{{ $errors->has('first_name') ? ' has-error' : '' }}">
                                    <label for="pm-first-name" class="col-sm-4 control-label">{{ __('First name') }}</label>
                                    <div class="col-sm-6">
                                        <input id="pm-first-name" name="first_name" type="text" class="form-control input-sized" value="{{ old('first_name') }}"
                                               maxlength="20" required autocomplete="off" data-label="{{ __('Enter their first name.') }}">
                                        @include('partials/field_error', ['field' => 'first_name'])
                                    </div>
                                </div>
                                <div class="form-group{{ $errors->has('last_name') ? ' has-error' : '' }}">
                                    <label for="pm-last-name" class="col-sm-4 control-label">{{ __('Last name') }}</label>
                                    <div class="col-sm-6">
                                        <input id="pm-last-name" name="last_name" type="text" class="form-control input-sized" value="{{ old('last_name') }}"
                                               maxlength="30" autocomplete="off">
                                        @include('partials/field_error', ['field' => 'last_name'])
                                    </div>
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('local') || $errors->has('domain') ? ' has-error' : '' }}">
                                <label for="pm-local" class="col-sm-4 control-label">{{ __('Address') }}</label>
                                <div class="col-sm-6">
                                    <div class="input-group input-sized">
                                        <input id="pm-local" name="local" type="text" class="form-control" value="{{ old('local') }}"
                                               maxlength="64" required pattern="[a-z0-9]([a-z0-9._\-]*[a-z0-9])?" autocomplete="off" placeholder="sales"
                                               data-label="{{ __('Enter the address, using lowercase letters, digits, dots, dashes or underscores.') }}">
                                        @if (count($domains) === 1)
                                            {{-- One domain: show it as text (a select in an addon cell collapses to just its arrow). --}}
                                            <span class="input-group-addon" id="pm-domain">{{ '@'.$domains[0] }}</span>
                                            <input type="hidden" name="domain" value="{{ $domains[0] }}">
                                        @else
                                            <span class="input-group-addon" style="padding:0">
                                                <select id="pm-domain" name="domain" class="form-control" style="border:0;height:32px;width:auto;min-width:150px">
                                                    @foreach ($domains as $d)
                                                        <option value="{{ $d }}" @if (old('domain') == $d) selected @endif>{{ '@'.$d }}</option>
                                                    @endforeach
                                                </select>
                                            </span>
                                        @endif
                                    </div>
                                    @include('partials/field_error', ['field' => 'local'])
                                    @include('partials/field_error', ['field' => 'domain'])
                                    <p class="block-help">{{ __('Lowercase letters, digits, dots, dashes or underscores.') }}
                                        <span data-kind="person" @if ($kind !== 'person') hidden @endif>{{ __('People get first.last, filled in from their name.') }}</span></p>
                                </div>
                            </div>

                            <div data-kind="shared" @if ($kind === 'person') hidden @endif>
                                <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                                    <label for="pm-name" class="col-sm-4 control-label">{{ __('Mailbox name') }}</label>
                                    <div class="col-sm-6">
                                        <input id="pm-name" name="name" type="text" class="form-control input-sized" value="{{ old('name') }}"
                                               maxlength="40" required placeholder="Sales" data-label="{{ __('Enter a mailbox name.') }}">
                                        @include('partials/field_error', ['field' => 'name'])
                                    </div>
                                </div>

                                <div class="form-group{{ $errors->has('users') ? ' has-error' : '' }}">
                                    <label class="col-sm-4 control-label" id="pm-users">{{ __('Who can see it') }}</label>
                                    <div class="col-sm-6">
                                        <p class="block-help">{{ __('Administrators always can.') }}</p>
                                        @foreach ($users as $u)
                                            @if (!$u->isAdmin())
                                                <div class="checkbox"><label>
                                                    <input type="checkbox" name="users[]" value="{{ $u->id }}" @if (in_array($u->id, old('users', []))) checked @endif>
                                                    {{ $u->getFullName() }} <span class="text-help">{{ $u->email }}</span>
                                                </label></div>
                                            @endif
                                        @endforeach
                                        @include('partials/field_error', ['field' => 'users'])
                                    </div>
                                </div>
                            </div>

                            <div data-kind="person" @if ($kind !== 'person') hidden @endif>
                                <div class="form-group{{ $errors->has('mailboxes') ? ' has-error' : '' }}">
                                    <label class="col-sm-4 control-label" id="pm-mailboxes">{{ __('Shared inboxes they see') }}</label>
                                    <div class="col-sm-6">
                                        @foreach ($mailboxes as $m)
                                            <div class="checkbox"><label>
                                                <input type="checkbox" name="mailboxes[]" value="{{ $m->id }}" @if (in_array($m->id, old('mailboxes', []))) checked @endif>
                                                {{ $m->name }} <span class="text-help">{{ $m->email }}</span>
                                            </label></div>
                                        @endforeach
                                        @include('partials/field_error', ['field' => 'mailboxes'])
                                    </div>
                                </div>

                                <div class="form-group{{ $errors->has('role') ? ' has-error' : '' }}">
                                    <label for="pm-role" class="col-sm-4 control-label">{{ __('Role') }}</label>
                                    <div class="col-sm-6">
                                        <select id="pm-role" name="role" class="form-control input-sized">
                                            <option value="user" @if (old('role', 'user') === 'user') selected @endif>{{ __('User: sees only the inboxes ticked above') }}</option>
                                            <option value="admin" @if (old('role') === 'admin') selected @endif>{{ __('Administrator: sees and manages everything') }}</option>
                                        </select>
                                        @include('partials/field_error', ['field' => 'role'])
                                    </div>
                                </div>

                                <div class="form-group{{ $errors->has('send_to') ? ' has-error' : '' }}">
                                    <label for="pm-send-to" class="col-sm-4 control-label">{{ __('Email login details to') }}</label>
                                    <div class="col-sm-6">
                                        <input id="pm-send-to" name="send_to" type="email" class="form-control input-sized" value="{{ old('send_to') }}"
                                               maxlength="100" autocomplete="off" placeholder="name@gmail.com"
                                               data-label="{{ __('Enter a valid personal email, like name@gmail.com.') }}">
                                        @include('partials/field_error', ['field' => 'send_to'])
                                        <p class="block-help">{{ __('Their personal email. They get the address, password and links, and can reset a forgotten password with it. Leave empty to hand the details over yourself.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group margin-top">
                                <div class="col-sm-6 col-sm-offset-4">
                                    <button type="submit" class="btn btn-primary" id="pm-submit"
                                            data-shared="{{ __('Create inbox') }}" data-person="{{ __('Create address and sign-in') }}">
                                        {{ $kind === 'person' ? __('Create address and sign-in') : __('Create inbox') }}</button>
                                </div>
                            </div>
                        </form>

                        @if (count($unconfigured))
                            <hr>
                            <h3>{{ __('Mailboxes here that are not connected yet') }}</h3>
                            @foreach ($unconfigured as $m)
                                <form class="form-inline margin-bottom" method="POST" action="{{ route('purelymail.setup') }}">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="mailbox_id" value="{{ $m->id }}">
                                    <strong style="display:inline-block;min-width:230px">{{ $m->email }}</strong>
                                    <button type="submit" class="btn btn-primary">{{ __('Set up on Purelymail') }}</button>
                                </form>
                            @endforeach
                        @endif

                        @if (count($unconnected))
                            <hr>
                            <h3>{{ __('On Purelymail but not here yet') }}</h3>
                            @foreach ($unconnected as $address)
                                <form class="form-inline margin-bottom" method="POST" action="{{ route('purelymail.connect') }}">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="address" value="{{ $address }}">
                                    <strong style="display:inline-block;min-width:230px">{{ $address }}</strong>
                                    <input type="text" name="name" class="form-control" required maxlength="40"
                                           value="{{ ucfirst(strstr($address, '@', true)) }}" aria-label="{{ __('Mailbox name for :a', ['a' => $address]) }}">
                                    <button type="submit" class="btn btn-default">{{ __('Connect') }}</button>
                                </form>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
    @parent
    (function () {
        var form = document.getElementById('pm-form');
        if (!form) { return; }
        var box = document.getElementById('pm-errors'), list = document.getElementById('pm-error-list');
        var local = document.getElementById('pm-local'), localTouched = local.value !== '';
        function kind() { var r = form.querySelector('input[name=kind]:checked'); return r ? r.value : 'shared'; }
        function apply() {
            var k = kind();
            [].forEach.call(form.querySelectorAll('[data-kind]'), function (el) {
                var on = el.getAttribute('data-kind') === k;
                el.hidden = !on;
                // Hidden fields are not sent or checked, so the draft of the other kind stays put.
                [].forEach.call(el.querySelectorAll('input,select'), function (i) { i.disabled = !on; });
            });
            var b = document.getElementById('pm-submit');
            b.textContent = b.getAttribute(k === 'person' ? 'data-person' : 'data-shared');
            local.placeholder = k === 'person' ? 'first.last' : 'sales';
        }
        function slug(s) {
            return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '');
        }
        function suggest() {
            if (kind() !== 'person' || localTouched) { return; }
            var f = slug(document.getElementById('pm-first-name').value), l = slug(document.getElementById('pm-last-name').value);
            local.value = f && l ? f + '.' + l : f;
        }
        local.addEventListener('input', function () { localTouched = local.value !== ''; });
        ['pm-first-name', 'pm-last-name'].forEach(function (id) { document.getElementById(id).addEventListener('input', suggest); });
        [].forEach.call(form.querySelectorAll('input[name=kind]'), function (r) { r.addEventListener('change', function () { apply(); suggest(); }); });
        form.addEventListener('submit', function (ev) {
            var problems = [];
            [].forEach.call(form.querySelectorAll('input[data-label]'), function (i) {
                var group = i.closest('.form-group');
                if (i.disabled) { return; }
                var bad = (i.required && !i.value.trim()) || !i.checkValidity() ||
                    (i.pattern && i.value && !new RegExp('^(?:' + i.pattern + ')$').test(i.value));
                group.classList.toggle('has-error', bad);
                if (bad) { problems.push(i); }
            });
            if (!problems.length) { return; }
            ev.preventDefault();
            list.innerHTML = '';
            problems.forEach(function (i) {
                var li = document.createElement('li'), a = document.createElement('a');
                a.href = '#' + i.id; a.textContent = i.getAttribute('data-label');
                li.appendChild(a); list.appendChild(li);
            });
            box.hidden = false; box.focus();
        });
        apply();
        if (!box.hidden) { box.focus(); }
    })();
@endsection
