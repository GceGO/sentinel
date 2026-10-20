@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="clock" :title="__('gcego-sentinel::messages.expired.heading')" :description="__('gcego-sentinel::messages.expired.description')" />
    <ul class="{{ $s('description') }} mb-6 list-disc ps-5">
        <li>{{ __('gcego-sentinel::messages.expired.tip_reload') }}</li>
        <li>{{ __('gcego-sentinel::messages.expired.tip_cookies') }}</li>
    </ul>
    @if (($login = $context->loginUrl()) !== null)
        <a href="{{ $login }}" class="{{ $s('button', 'button.primary', 'button.block') }}">{{ __('gcego-sentinel::messages.expired.submit') }}</a>
    @endif
</x-gcego-sentinel::page>
