@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<p class="{{ $s('divider') }}">{{ $slot }}</p>
