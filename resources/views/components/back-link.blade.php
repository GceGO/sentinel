@props(['href', 'label'])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<a href="{{ $href }}" class="{{ $s('back') }}"><x-gcego-sentinel::icon name="arrow-left" :class="$s('back.icon')" />{{ $label }}</a>
