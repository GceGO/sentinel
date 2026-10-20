@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<x-gcego-sentinel::document :context="$context">
    <div class="{{ $s('layout.minimal') }}" data-sentinel-layout="minimal">
        @if ($context->showBranding)
            <div class="mb-6"><x-gcego-sentinel::brand :context="$context" /></div>
        @endif
        <main id="sentinel-main" tabindex="-1" aria-labelledby="sentinel-heading" class="{{ $s('card.flat') }} focus:outline-none">
            {{ $slot }}
        </main>
        <x-gcego-sentinel::footer :context="$context" />
    </div>
</x-gcego-sentinel::document>
