@props(['title', 'description' => null, 'icon' => null, 'tone' => null])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<header class="{{ $s('header') }}">
    @if ($icon !== null)
        <span class="{{ $s($tone === null ? 'icon' : 'icon.'.$tone) }}"><x-gcego-sentinel::icon :name="$icon" :class="$s('icon.svg')" /></span>
    @endif
    <h1 class="{{ $s('heading') }}" id="sentinel-heading">{{ $title }}</h1>
    @if ($description !== null)
        <p class="{{ $s('description') }}">{{ $description }}</p>
    @endif
    {{ $slot }}
</header>
