@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $branding = $context->branding;
@endphp
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="blocked" tone="danger" :title="__('gcego-sentinel::messages.account.'.$state->value.'_heading')" :description="__('gcego-sentinel::messages.account.'.$state->value.'_description')" />

    @if ($branding->supportUrl !== null || $branding->supportEmail !== null)
        <x-gcego-sentinel::status tone="info">
            {{ __('gcego-sentinel::messages.account.contact') }}
            @if ($branding->supportUrl !== null)
                <a href="{{ $branding->supportUrl }}" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.footer.support') }}</a>
            @else
                <a href="mailto:{{ $branding->supportEmail }}" class="{{ $s('link') }}">{{ $branding->supportEmail }}</a>
            @endif
        </x-gcego-sentinel::status>
    @endif

    @if (($login = $context->loginUrl()) !== null)
        <p class="mt-6"><x-gcego-sentinel::back-link :href="$login" :label="__('gcego-sentinel::messages.back_to_login')" /></p>
    @endif
</x-gcego-sentinel::page>
