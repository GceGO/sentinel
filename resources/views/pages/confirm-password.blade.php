@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="shield" :title="__('gcego-sentinel::messages.confirm.heading')" :description="__('gcego-sentinel::messages.confirm.description')" />

    <form method="POST" action="{{ $context->url('password.confirm.store') ?? url('/user/confirm-password') }}" class="{{ $s('form') }}" data-sentinel-form>
        @csrf
        <x-gcego-sentinel::errors :inline="['password']" />
        <x-gcego-sentinel::password :label="__('gcego-sentinel::messages.fields.password')" autocomplete="current-password" :autofocus="true" />
        <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.confirm.submit') }}</x-gcego-sentinel::button>
    </form>
</x-gcego-sentinel::page>
