@props(['id', 'title', 'description' => null, 'icon' => null])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<div class="{{ $s('section.header') }}">
    @if ($icon !== null)
        <span class="{{ $s('section.icon') }}"><x-gcego-sentinel::icon :name="$icon" :class="$s('section.icon.svg')" /></span>
    @endif
    <div class="{{ $s('section.heading') }}">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h2 id="{{ $id }}" class="{{ $s('section.title') }}">{{ $title }}</h2>
            {{ $slot }}
        </div>
        @if ($description !== null)
            <p class="{{ $s('section.description') }}">{{ $description }}</p>
        @endif
    </div>
</div>
