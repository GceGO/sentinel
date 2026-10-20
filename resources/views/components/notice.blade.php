@props(['icon' => 'shield', 'message' => null])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<p {{ $attributes->class([$s('notice')]) }}><x-gcego-sentinel::icon :name="$icon" :class="$s('notice.icon')" /><span>{{ $message ?? $slot }}</span></p>
