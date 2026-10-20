@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="lock" :title="__('gcego-sentinel::messages.reset.heading')" :description="__('gcego-sentinel::messages.reset.description')" />

    <form method="POST" action="{{ $context->url('password.update') ?? url('/reset-password') }}" class="{{ $s('form') }}" data-sentinel-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-gcego-sentinel::errors :inline="['email', 'password', 'password_confirmation']" />
        <x-gcego-sentinel::input name="email" type="email" :label="__('gcego-sentinel::messages.fields.email')" :value="old('email', $email)" autocomplete="email" inputmode="email" :required="true" maxlength="255" icon="mail" />
        <x-gcego-sentinel::password :label="__('gcego-sentinel::messages.fields.new_password')" autocomplete="new-password" :guidance="true" :autofocus="true" />
        <x-gcego-sentinel::password name="password_confirmation" :label="__('gcego-sentinel::messages.fields.password_confirmation')" autocomplete="new-password" />
        <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.reset.submit') }}</x-gcego-sentinel::button>
    </form>

    @if (($forgot = $context->forgotPasswordUrl()) !== null)
        <p class="{{ $s('footer') }}">{{ __('gcego-sentinel::messages.reset.expired') }} <a href="{{ $forgot }}" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.reset.request_new') }}</a></p>
    @endif
</x-gcego-sentinel::page>
