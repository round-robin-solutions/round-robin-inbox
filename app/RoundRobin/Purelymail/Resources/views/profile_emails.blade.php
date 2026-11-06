{{-- Profile » personal email (setup only), above FreeScout's own Email row (relabelled Round Robin email (sign-in)). --}}
<div class="form-group{{ $errors->has('personal_email') ? ' has-error' : '' }}">
    <label for="personal_email" class="col-sm-2 control-label">{{ __('Personal email (setup only)') }}</label>
    <div class="col-sm-6">
        <input id="personal_email" type="email" class="form-control input-sized" name="personal_email" maxlength="100"
               value="{{ old('personal_email', \App\RoundRobin\Purelymail\Services\Addresses::personal($user)) }}" placeholder="name@gmail.com" autocomplete="email"
               @if ($errors->has('personal_email')) aria-invalid="true" aria-describedby="personal_email-error" @endif>
        @if ($errors->has('personal_email'))<span class="help-block" id="personal_email-error">{{ $errors->first('personal_email') }}</span>@endif
        <p class="help-block">{{ __('Only used to send the one-time setup (login details) for the Round Robin account. Not a sign-in.') }}</p>
    </div>
</div>
