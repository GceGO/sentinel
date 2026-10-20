@props(['tone' => 'neutral'])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<span {{ $attributes->class([$s('badge'), $s('badge.'.$tone)]) }}><span class="{{ $s('badge.dot') }}" aria-hidden="true"></span>{{ $slot }}</span>
