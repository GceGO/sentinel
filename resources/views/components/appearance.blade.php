@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $switching = app(\Gcego\Chroma\ChromaManager::class)->allowsSwitching();
@endphp
@if ($switching)
    <div role="group" aria-label="{{ __('gcego-sentinel::messages.appearance.label') }}" class="{{ $s('appearance') }}" hidden data-sentinel-js>
        @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'] as $mode => $icon)
            <button type="button" class="{{ $s('appearance.button') }}" data-sentinel-appearance-option="{{ $mode }}" aria-pressed="false" aria-label="{{ __('gcego-sentinel::messages.appearance.'.$mode) }}" title="{{ __('gcego-sentinel::messages.appearance.'.$mode) }}">
                <x-gcego-sentinel::icon :name="$icon" :class="$s('appearance.icon')" />
            </button>
        @endforeach
    </div>
@endif
