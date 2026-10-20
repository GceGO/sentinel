@props(['svg', 'setupKey' => null])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<div class="{{ $s('setup') }}">
    {{-- Sentinel renders the SVG (bacon/bacon-qr-code) from the user's own pending secret. --}}
    <figure class="{{ $s('qr') }}" role="img" aria-label="{{ __('gcego-sentinel::messages.two_factor.qr_label') }}">{!! $svg !!}</figure>
    <div class="flex min-w-0 flex-col gap-2 text-sm text-gcego-text-muted">
        <p>{{ __('gcego-sentinel::messages.two_factor.scan') }}</p>
        @if ($setupKey !== null)
            <p>{{ __('gcego-sentinel::messages.two_factor.manual') }}<code class="{{ $s('key') }}">{{ $setupKey }}</code></p>
        @endif
    </div>
</div>
