@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<x-gcego-sentinel::document :context="$context">
    <div class="{{ $s('layout.centered') }}" data-sentinel-layout="centered">
        <div class="{{ $s('layout.centered.glow') }}" aria-hidden="true"></div>
        <div class="{{ $s('layout.centered.main') }}">
            @if ($context->showBranding)
                <div class="mb-8 flex justify-center"><x-gcego-sentinel::brand :context="$context" /></div>
            @endif
            <main id="sentinel-main" tabindex="-1" aria-labelledby="sentinel-heading" class="{{ $s('card') }} focus:outline-none">
                {{ $slot }}
            </main>
            <x-gcego-sentinel::footer :context="$context" />
        </div>
    </div>
</x-gcego-sentinel::document>
