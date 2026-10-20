@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $branding = $context->branding;
@endphp
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="key" :title="__('gcego-sentinel::messages.recovery.heading')" :description="__('gcego-sentinel::messages.recovery.description')" />

    <ol class="flex list-decimal flex-col gap-3 ps-5 text-sm text-gcego-text">
        <li>{{ __('gcego-sentinel::messages.recovery.step_codes') }}</li>
        @if ($context->has(\Gcego\Sentinel\Features\Feature::Passkeys))
            <li>{{ __('gcego-sentinel::messages.recovery.step_passkey') }}</li>
        @endif
        <li>{{ __('gcego-sentinel::messages.recovery.step_support') }}</li>
    </ol>

    <x-gcego-sentinel::status tone="info" class="mt-5" :message="__('gcego-sentinel::messages.recovery.never')" />

    @if ($branding->supportUrl !== null || $branding->supportEmail !== null)
        <p class="mt-5 text-sm">
            @if ($branding->supportUrl !== null)
                <a href="{{ $branding->supportUrl }}" class="{{ $s('link') }}">{{ __('gcego-sentinel::messages.footer.support') }}</a>
            @else
                <a href="mailto:{{ $branding->supportEmail }}" class="{{ $s('link') }}">{{ $branding->supportEmail }}</a>
            @endif
        </p>
    @endif

    @if (($login = $context->loginUrl()) !== null)
        <p class="mt-6"><x-gcego-sentinel::back-link :href="$login" :label="__('gcego-sentinel::messages.back_to_login')" /></p>
    @endif
</x-gcego-sentinel::page>
