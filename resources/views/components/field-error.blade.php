@props(['id', 'message'])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<p id="{{ $id }}" class="{{ $s('error') }}"><x-gcego-sentinel::icon name="alert" :class="$s('error.icon')" /><span>{{ $message }}</span></p>
