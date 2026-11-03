@extends('layouts.app')

@section('title_full', __('Login & password').' - '.$user->getFullName())

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('users/sidebar_menu')
@endsection

@section('content')
    <div class="section-heading">{{ __('Login & password') }}</div>

    <div class="container">
        <div class="row">
            <div class="col-xs-12 col-md-10 margin-top">
                @include('partials/flash_messages')

                @if ($r = session('purelymail_reset'))
                    <div class="alert alert-success" id="pm-reset-result" tabindex="-1">
                        <p><strong>{{ __('New password set for :name.', ['name' => $user->getFullName()]) }}</strong>
                            @if ($r['backed']) {{ __('It is also their email password now (webmail and phone).') }} @endif</p>
                        <p>{{ __('Shown once:') }} <code style="font-size:15px;user-select:all">{{ $r['password'] }}</code></p>
                        @if ($r['emailed'])
                            <p>{{ __('Emailed to :to.', ['to' => $r['emailed']]) }}</p>
                        @elseif ($r['send_to'])
                            <p class="text-danger">{{ __('Could not email it to :to (:error). Copy it and send it privately.', ['to' => $r['send_to'], 'error' => $r['mail_error']]) }}</p>
                        @else
                            <p>{{ __('Not emailed. Send it to them privately, for example on WhatsApp.') }}</p>
                        @endif
                        <p class="text-help">{{ __('Their old password and any "remember me" sign-ins stop working.') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" id="pm-errors" tabindex="-1">
                        <p><strong>{{ __('Please fix this:') }}</strong></p>
                        <ul>
                            @foreach (['send_to', 'recovery'] as $f)
                                @if ($errors->has($f))<li><a href="#{{ $f }}">{{ $errors->first($f) }}</a></li>@endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p>
                    <strong>{{ $user->email }}</strong>
                    @if ($backed === true)
                        · {{ __('One password for the team inbox, webmail and phone mail apps.') }}
                    @elseif ($backed === null)
                        · <span class="text-warning">{{ __('Purelymail is not answering right now.') }}</span>
                    @endif
                </p>

                {{-- Personal email: where "Forgot password" and new passwords go. --}}
                <h3>{{ __('Personal email for password resets') }}</h3>
                <p class="text-help">
                    {{ __('"Forgot password" on the sign-in page sends the reset link here, not to :address (reading that needs the password).', ['address' => $user->email]) }}
                    @if ($backed === true) {{ __('Purelymail\'s own "lost password" page for webmail uses it too.') }} @endif
                </p>
                <form class="form-horizontal" method="POST" action="{{ route('purelymail.login.recovery', ['id' => $user->id]) }}" novalidate>
                    {{ csrf_field() }}
                    <div class="form-group{{ $errors->has('recovery') ? ' has-error' : '' }}">
                        <label for="recovery" class="col-sm-2 control-label">{{ __('Personal email') }}</label>
                        <div class="col-sm-6">
                            <input id="recovery" type="email" class="form-control input-sized" name="recovery" maxlength="100"
                                   value="{{ old('recovery', $recovery) }}" placeholder="name@gmail.com" autocomplete="email"
                                   @if ($errors->has('recovery')) aria-invalid="true" aria-describedby="recovery-error" @endif>
                            @if ($errors->has('recovery'))<span class="help-block" id="recovery-error">{{ $errors->first('recovery') }}</span>@endif
                            @if (!$recovery)<p class="help-block">{{ __('Not set: "Forgot password" goes to :address.', ['address' => $user->email]) }}</p>@endif
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-6 col-sm-offset-2">
                            <button type="submit" class="btn btn-primary">{{ __('Save personal email') }}</button>
                        </div>
                    </div>
                </form>

                <h3>{{ __('Password') }}</h3>
                @if ($is_self)
                    <p><a href="{{ route('users.password', ['id' => $user->id]) }}">{{ __('Change your password') }}</a>
                        @if ($backed === true) {{ __('(changes it for webmail and your phone too)') }} @endif</p>
                @elseif (auth()->user()->isAdmin())
                    <p class="text-help">{{ __('Sets a new password straight away and shows it once. Emailing it is optional; leave the box empty to send it yourself.') }}
                        @if ($backed === true) {{ __('It becomes their email password too, so their phone will ask for it.') }} @endif</p>
                    <form class="form-horizontal" method="POST" action="{{ route('purelymail.login.reset', ['id' => $user->id]) }}" novalidate
                          onsubmit="return confirm('{{ __('Set a new password for :name? Their current password stops working.', ['name' => addslashes($user->getFullName())]) }}')">
                        {{ csrf_field() }}
                        <div class="form-group{{ $errors->has('send_to') ? ' has-error' : '' }}">
                            <label for="send_to" class="col-sm-2 control-label">{{ __('Email it to') }}</label>
                            <div class="col-sm-6">
                                <input id="send_to" type="email" class="form-control input-sized" name="send_to" maxlength="100"
                                       value="{{ old('send_to', $recovery) }}" placeholder="{{ __('their personal email (optional)') }}"
                                       @if ($errors->has('send_to')) aria-invalid="true" aria-describedby="send_to-error" @endif>
                                @if ($errors->has('send_to'))<span class="help-block" id="send_to-error">{{ $errors->first('send_to') }}</span>@endif
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-sm-6 col-sm-offset-2">
                                <button type="submit" class="btn btn-primary">{{ __('Reset password') }}</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('javascript')
    @parent
    var pmFocus = document.getElementById('pm-errors') || document.getElementById('pm-reset-result');
    if (pmFocus) { pmFocus.focus(); }
@endsection
