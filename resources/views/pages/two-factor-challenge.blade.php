@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $action = $context->url('two-factor.login.store') ?? url('/two-factor-challenge');
    $page = $context->url('two-factor.login') ?? url('/two-factor-challenge');
@endphp
<x-gcego-sentinel::page :context="$context">
    @if ($recovery)
        <x-gcego-sentinel::header icon="key" :title="__('gcego-sentinel::messages.challenge.recovery_heading')" :description="__('gcego-sentinel::messages.challenge.recovery_description')" />
    @else
        <x-gcego-sentinel::header icon="shield" :title="__('gcego-sentinel::messages.challenge.heading')" :description="__('gcego-sentinel::messages.challenge.description')" />
    @endif

    <form method="POST" action="{{ $action }}" class="{{ $s('form') }}" data-sentinel-form>
        @csrf
        <x-gcego-sentinel::errors :inline="['code', 'recovery_code']" />
        @if ($recovery)
            <x-gcego-sentinel::input name="recovery_code" :label="__('gcego-sentinel::messages.fields.recovery_code')" autocomplete="off" :required="true" :autofocus="true" maxlength="64" spellcheck="false" autocapitalize="characters" icon="key" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" class="font-mono tracking-wide" />
        @else
            <x-gcego-sentinel::input name="code" :label="__('gcego-sentinel::messages.fields.code')" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9 ]*" :required="true" :autofocus="true" maxlength="10" :code="true" placeholder="000000" :help="__('gcego-sentinel::messages.challenge.help')" />
        @endif
        <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.challenge.submit') }}</x-gcego-sentinel::button>
    </form>

    <p class="{{ $s('footer') }}">
        @if ($recovery)
            <a href="{{ $page }}" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.challenge.use_code') }}</a>
        @else
            <a href="{{ $page }}?recovery=1" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.challenge.use_recovery') }}</a>
        @endif
    </p>
    @if (($recoveryHelp = $context->url('sentinel.account.recovery')) !== null)
        <p class="mt-2 text-center text-sm"><a href="{{ $recoveryHelp }}" class="{{ $s('link.muted') }}">{{ __('gcego-sentinel::messages.challenge.no_access') }}</a></p>
    @endif
    @if (($login = $context->loginUrl()) !== null)
        <p class="mt-4 text-center"><x-gcego-sentinel::back-link :href="$login" :label="__('gcego-sentinel::messages.back_to_login')" /></p>
    @endif
</x-gcego-sentinel::page>
