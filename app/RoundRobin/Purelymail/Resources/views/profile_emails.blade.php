{{-- Profile » the two addresses, above FreeScout's own (sign-in) Email row. --}}
@php
    $pm_admin = auth()->user()->isAdmin();
    $pm_signin = \App\RoundRobin\Purelymail\Services\Addresses::workIsSignIn($user);
    $pm_work = \App\RoundRobin\Purelymail\Services\Addresses::work($user);
    $pm_personal = \App\RoundRobin\Purelymail\Services\Addresses::personal($user);
    $pm_work_editable = $pm_admin && !$pm_signin;
@endphp
<div class="form-group{{ $errors->has('rr_email') ? ' has-error' : '' }}">
    <label for="rr_email" class="col-sm-2 control-label">{{ __('Round Robin email') }}</label>
    <div class="col-sm-6">
        <input id="rr_email" type="email" class="form-control input-sized" name="rr_email" maxlength="100"
               value="{{ old('rr_email', $pm_work) }}" placeholder="name@roundrobin.in"
               @if (!$pm_work_editable) readonly @endif
               @if ($errors->has('rr_email')) aria-invalid="true" aria-describedby="rr_email-error" @endif>
        @if ($errors->has('rr_email'))<span class="help-block" id="rr_email-error">{{ $errors->first('rr_email') }}</span>@endif
        @if ($pm_signin)<p class="help-block">{{ __('This is also the sign-in email below.') }}</p>@endif
    </div>
</div>
<div class="form-group{{ $errors->has('personal_email') ? ' has-error' : '' }}">
    <label for="personal_email" class="col-sm-2 control-label">{{ __('Personal email') }}</label>
    <div class="col-sm-6">
        <input id="personal_email" type="email" class="form-control input-sized" name="personal_email" maxlength="100"
               value="{{ old('personal_email', $pm_personal) }}" placeholder="name@gmail.com" autocomplete="email"
               @if ($errors->has('personal_email')) aria-invalid="true" aria-describedby="personal_email-error" @endif>
        @if ($errors->has('personal_email'))<span class="help-block" id="personal_email-error">{{ $errors->first('personal_email') }}</span>@endif
        <p class="help-block">{{ __('Where "Forgot password" and new passwords are sent.') }}</p>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var l = document.querySelector('label[for="email"]');
        if (l) { l.textContent = @json(__('Sign-in email')); }
    });
</script>
