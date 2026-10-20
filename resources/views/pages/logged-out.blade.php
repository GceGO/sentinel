@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="check" tone="success" :title="__('gcego-sentinel::messages.logged_out.heading')" :description="__('gcego-sentinel::messages.logged_out.description', ['name' => $context->branding->name])" />
    @if (($login = $context->loginUrl()) !== null)
        <a href="{{ $login }}" class="{{ $s('button', 'button.primary', 'button.block') }}">{{ __('gcego-sentinel::messages.logged_out.submit') }}</a>
    @endif
    <p class="{{ $s('help') }} mt-6">{{ __('gcego-sentinel::messages.logged_out.help') }}</p>
</x-gcego-sentinel::page>
