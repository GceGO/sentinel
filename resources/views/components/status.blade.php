@props(['tone' => 'success', 'message' => null])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $icon = ['success' => 'check', 'danger' => 'alert', 'info' => 'info', 'warning' => 'warning'][$tone] ?? 'info';
@endphp
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class([$s('alert'), $s('alert.'.$tone)]) }}>
    <x-gcego-sentinel::icon :name="$icon" :class="$s('alert.icon').' '.$s('alert.icon.'.$tone)" />
    <div class="min-w-0 flex-1">{{ $message ?? $slot }}</div>
</div>
