@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $config = app(\Gcego\Sentinel\SentinelConfig::class);
    $login = $context->loginUrl();
@endphp
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header
        :title="__('gcego-sentinel::messages.register.heading')"
        :description="__('gcego-sentinel::messages.register.description', ['name' => $context->branding->name])"
    />

    <form method="POST" action="{{ $context->url('register.store') ?? url('/register') }}" class="{{ $s('form') }}" data-sentinel-form>
        @csrf
        <x-gcego-sentinel::errors :inline="['name', 'email', 'password', 'password_confirmation', 'terms', ...array_map(fn ($field) => $field->name, $config->registrationFields)]" />

        <x-gcego-sentinel::input name="name" :label="__('gcego-sentinel::messages.fields.name')" :value="old('name')" autocomplete="name" :required="true" :autofocus="true" maxlength="255" icon="user" />
        <x-gcego-sentinel::input name="email" type="email" :label="__('gcego-sentinel::messages.fields.email')" :value="old('email')" autocomplete="email" inputmode="email" :required="true" maxlength="255" icon="mail" />

        @foreach ($config->registrationFields as $field)
            <x-gcego-sentinel::input :name="$field->name" :type="$field->type" :label="$field->label()" :value="old($field->name)" :autocomplete="$field->autocomplete" :required="$field->required" :help="$field->help()" />
        @endforeach

        <x-gcego-sentinel::password :label="__('gcego-sentinel::messages.fields.new_password')" autocomplete="new-password" :guidance="true" />
        <x-gcego-sentinel::password name="password_confirmation" :label="__('gcego-sentinel::messages.fields.password_confirmation')" autocomplete="new-password" />

        @if ($config->termsUrl !== null)
            <x-gcego-sentinel::checkbox name="terms" :checked="(bool) old('terms')" :required="true">
                {{ __('gcego-sentinel::messages.register.terms_before') }} <a href="{{ $config->termsUrl }}" class="{{ $s('link') }}" target="_blank" rel="noopener noreferrer">{{ __('gcego-sentinel::messages.register.terms_link') }}</a>
            </x-gcego-sentinel::checkbox>
        @endif

        <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.register.submit') }}</x-gcego-sentinel::button>
    </form>

    @if ($login !== null)
        <p class="{{ $s('footer') }}">{{ __('gcego-sentinel::messages.register.have_account') }} <a href="{{ $login }}" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.register.login') }}</a></p>
    @endif
</x-gcego-sentinel::page>
