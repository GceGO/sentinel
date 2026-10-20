@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $branding = $context->branding;
@endphp
<x-gcego-sentinel::document :context="$context">
    <div class="{{ $s('layout.branded') }}" data-sentinel-layout="branded">
        <div class="{{ $s('layout.branded.backdrop') }}" aria-hidden="true"></div>
        @if ($branding->background !== null)
            <img src="{{ $branding->background }}" alt="" class="{{ $s('layout.branded.image') }}">
        @endif
        <div class="{{ $s('layout.branded.overlay') }}" aria-hidden="true"></div>
        <div class="{{ $s('layout.branded.main') }}">
            @if ($context->showBranding)
                <div class="mb-8 flex justify-center text-white"><x-gcego-sentinel::brand :context="$context" :inverse="true" /></div>
            @endif
            <main id="sentinel-main" tabindex="-1" aria-labelledby="sentinel-heading" class="{{ $s('card') }} focus:outline-none">
                {{ $slot }}
            </main>
            <x-gcego-sentinel::footer :context="$context" :inverse="true" />
        </div>
    </div>
</x-gcego-sentinel::document>
