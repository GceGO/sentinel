@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $status = \Gcego\Sentinel\Support\StatusMessages::for(session('status'));
    $login = $context->loginUrl();
@endphp
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="key" :title="__('gcego-sentinel::messages.forgot.heading')" :description="__('gcego-sentinel::messages.forgot.description')" />

    @if ($status !== null)
        <x-gcego-sentinel::status class="mb-5" :message="$status" />
    @endif

    <form method="POST" action="{{ $context->url('password.email') ?? url('/forgot-password') }}" class="{{ $s('form') }}" data-sentinel-form>
        @csrf
        <x-gcego-sentinel::errors :inline="['email']" />
        <x-gcego-sentinel::input name="email" type="email" :label="__('gcego-sentinel::messages.fields.email')" :value="old('email')" autocomplete="email" inputmode="email" :required="true" :autofocus="true" maxlength="255" icon="mail" />
        <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.forgot.submit') }}</x-gcego-sentinel::button>
    </form>

    @if ($login !== null)
        <p class="mt-6"><x-gcego-sentinel::back-link :href="$login" :label="__('gcego-sentinel::messages.back_to_login')" /></p>
    @endif
</x-gcego-sentinel::page>
