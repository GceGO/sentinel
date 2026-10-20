@props(['variant' => 'primary', 'type' => 'submit', 'block' => true, 'small' => false, 'icon' => null, 'loading' => null])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<button type="{{ $type }}" {{ $attributes->class([$s('button'), $s('button.'.$variant), $s('button.block') => $block, $s('button.small') => $small]) }}>
    @if ($loading !== null)
        <x-gcego-sentinel::icon name="spinner" :class="$s('spinner.livewire')" wire:loading wire:target="{{ $loading }}" />
    @elseif ($type === 'submit')
        <x-gcego-sentinel::icon name="spinner" :class="$s('spinner')" />
    @endif
    @if ($icon !== null)
        <x-gcego-sentinel::icon :name="$icon" class="size-4" />
    @endif
    <span>{{ $slot }}</span>
</button>
