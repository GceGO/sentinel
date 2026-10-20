@props(['codes', 'id' => 'sentinel-recovery-codes'])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<div class="flex flex-col gap-3">
    <ul id="{{ $id }}" class="{{ $s('codes') }}" aria-label="{{ __('gcego-sentinel::messages.two_factor.codes_label') }}" data-sentinel-sensitive>
        @foreach ($codes as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>
    <div class="{{ $s('codes.actions') }}" hidden data-sentinel-js>
        <x-gcego-sentinel::button type="button" variant="secondary" :block="false" :small="true" icon="copy" data-sentinel-copy="{{ $id }}" data-copied="{{ __('gcego-sentinel::messages.two_factor.copied') }}">{{ __('gcego-sentinel::messages.two_factor.copy') }}</x-gcego-sentinel::button>
        <x-gcego-sentinel::button type="button" variant="secondary" :block="false" :small="true" icon="printer" data-sentinel-print>{{ __('gcego-sentinel::messages.two_factor.print') }}</x-gcego-sentinel::button>
    </div>
</div>
