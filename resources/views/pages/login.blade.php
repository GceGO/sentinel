@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $config = app(\Gcego\Sentinel\SentinelConfig::class);
    $username = $config->identifier;
    $passkeys = $context->has(\Gcego\Sentinel\Features\Feature::Passkeys) ? $context->url('passkey.login-options') : null;
    $status = \Gcego\Sentinel\Support\StatusMessages::for(session('status'));
    $forgot = $context->forgotPasswordUrl();
    $register = $context->registerUrl();
@endphp
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header
        :title="__('gcego-sentinel::messages.login.heading')"
        :description="$context->panelTitle() !== null ? __('gcego-sentinel::messages.login.panel_description', ['panel' => $context->panelTitle()]) : __('gcego-sentinel::messages.login.description', ['name' => $context->branding->name])"
    />

    @if ($status !== null)
        <x-gcego-sentinel::status class="mb-5" :message="$status" />
    @endif

    <form method="POST" action="{{ $context->url('login.store') ?? url('/login') }}" class="{{ $s('form') }}" data-sentinel-form>
        @csrf
        <x-gcego-sentinel::errors :inline="[$username, 'password', 'remember']" />

        <x-gcego-sentinel::input
            :name="$username"
            :type="$username === 'email' ? 'email' : 'text'"
            :label="$username === 'email' ? __('gcego-sentinel::messages.fields.email') : __('gcego-sentinel::messages.fields.username')"
            :value="old($username)"
            autocomplete="username"
            :inputmode="$username === 'email' ? 'email' : null"
            :required="true"
            :autofocus="true"
            maxlength="255"
            :icon="$username === 'email' ? 'mail' : 'user'"
        />

        <x-gcego-sentinel::password :label="__('gcego-sentinel::messages.fields.password')" autocomplete="current-password">
            @if ($forgot !== null)
                <x-slot:action>
                    <a href="{{ $forgot }}" class="{{ $s('link.muted') }}">{{ __('gcego-sentinel::messages.login.forgot') }}</a>
                </x-slot:action>
            @endif
        </x-gcego-sentinel::password>

        @if ($config->remember)
            <x-gcego-sentinel::checkbox name="remember" :checked="(bool) old('remember')">{{ __('gcego-sentinel::messages.login.remember') }}</x-gcego-sentinel::checkbox>
        @endif

        <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.login.submit') }}</x-gcego-sentinel::button>
    </form>

    @if ($passkeys !== null && ($passkeyLogin = $context->url('passkey.login')) !== null)
        <x-gcego-sentinel::divider>{{ __('gcego-sentinel::messages.login.or') }}</x-gcego-sentinel::divider>
        <div class="flex flex-col gap-2" data-sentinel-passkey-login data-options-url="{{ $passkeys }}" data-login-url="{{ $passkeyLogin }}" hidden data-sentinel-js>
            <x-gcego-sentinel::button type="button" variant="secondary" icon="key" data-sentinel-passkey-start>{{ __('gcego-sentinel::messages.passkeys.sign_in') }}</x-gcego-sentinel::button>
            <p class="{{ $s('help') }}" role="status" aria-live="polite" data-sentinel-passkey-status data-unsupported="{{ __('gcego-sentinel::messages.passkeys.browser_unsupported') }}" data-failed="{{ __('gcego-sentinel::messages.passkeys.not_recognized') }}" data-working="{{ __('gcego-sentinel::messages.passkeys.waiting') }}"></p>
        </div>
    @endif

    @if ($register !== null)
        <p class="{{ $s('footer') }}">{{ __('gcego-sentinel::messages.login.no_account') }} <a href="{{ $register }}" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.login.register') }}</a></p>
    @endif
</x-gcego-sentinel::page>
