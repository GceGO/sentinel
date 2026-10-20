@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $branding = $context->branding;
    $highlights = array_values(array_filter([
        $context->has(\Gcego\Sentinel\Features\Feature::TwoFactorAuthentication)
            ? ['icon' => 'shield', 'text' => $context->has(\Gcego\Sentinel\Features\Feature::Passkeys) ? __('gcego-sentinel::messages.brand.highlights.two_factor_passkeys') : __('gcego-sentinel::messages.brand.highlights.two_factor')]
            : null,
        ['icon' => 'desktop', 'text' => __('gcego-sentinel::messages.brand.highlights.sessions')],
        app(\Gcego\Sentinel\SentinelConfig::class)->audit ? ['icon' => 'clock', 'text' => __('gcego-sentinel::messages.brand.highlights.activity')] : null,
    ]));
@endphp
<x-gcego-sentinel::document :context="$context">
    <div class="{{ $s('layout.split') }}" data-sentinel-layout="split">
        <div class="{{ $s('layout.split.main') }}">
            <div class="{{ $s('layout.split.accent') }}" aria-hidden="true"></div>
            @if ($context->showBranding)
                <x-gcego-sentinel::brand :context="$context" />
            @endif
            <div class="{{ $s('layout.split.content') }}">
                <main id="sentinel-main" tabindex="-1" aria-labelledby="sentinel-heading" class="{{ $s('card.flat') }} focus:outline-none">
                    {{ $slot }}
                </main>
            </div>
            <x-gcego-sentinel::footer :context="$context" />
        </div>
        @if ($context->showBranding)
            <aside class="{{ $s('layout.split.aside') }}" aria-label="{{ $branding->name }}">
                <div class="{{ $s('layout.split.backdrop') }}" aria-hidden="true"></div>
                @if ($branding->background !== null)
                    <img src="{{ $branding->background }}" alt="" class="{{ $s('layout.split.image') }}">
                @endif
                <div class="{{ $s('layout.split.sheen') }}" aria-hidden="true"></div>
                <x-gcego-sentinel::brand-art :class="$s('layout.split.art')" />
                <x-gcego-sentinel::brand :context="$context" :inverse="true" />
                <div class="{{ $s('layout.split.story') }}">
                    @if ($context->panelTitle() !== null)
                        <span class="{{ $s('layout.split.eyebrow') }}">{{ $context->panelTitle() }}</span>
                    @endif
                    <p class="{{ $s('layout.split.headline') }}">{{ $branding->tagline ?? __('gcego-sentinel::messages.brand.headline', ['name' => $branding->name]) }}</p>
                    <p class="{{ $s('layout.split.tagline') }}">{{ $context->panelTitle() !== null ? __('gcego-sentinel::messages.brand.panel', ['panel' => $context->panelTitle()]) : __('gcego-sentinel::messages.brand.tagline') }}</p>
                    @if ($branding->illustration !== null)
                        <img src="{{ $branding->illustration }}" alt="" class="{{ $s('layout.split.illustration') }}">
                    @else
                        <ul class="{{ $s('layout.split.highlights') }}">
                            @foreach ($highlights as $highlight)
                                <li class="{{ $s('layout.split.highlight') }}">
                                    <span class="{{ $s('layout.split.highlight.icon') }}"><x-gcego-sentinel::icon :name="$highlight['icon']" :class="$s('layout.split.highlight.svg')" /></span>
                                    <span>{{ $highlight['text'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <p class="{{ $s('layout.split.note') }}"><x-gcego-sentinel::icon name="lock" class="size-3.5" />{{ __('gcego-sentinel::messages.brand.secure') }}</p>
            </aside>
        @endif
    </div>
</x-gcego-sentinel::document>
