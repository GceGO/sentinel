@props(['context', 'inverse' => false])
@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
@php($branding = $context->branding)
<div class="{{ $s('brand') }}">
    @if ($branding->logo !== null)
        <img src="{{ $branding->logo }}" alt="{{ $branding->name }}" @class([$s('brand.logo'), 'dark:hidden' => $branding->logoDark !== null && ! $inverse])>
        @if ($branding->logoDark !== null && ! $inverse)
            <img src="{{ $branding->logoDark }}" alt="{{ $branding->name }}" class="{{ $s('brand.logo') }} hidden dark:block">
        @endif
    @else
        <span class="{{ $s($inverse ? 'brand.mark.inverse' : 'brand.mark') }}" aria-hidden="true">{{ $branding->initials() }}</span>
    @endif
    <span class="flex min-w-0 flex-col">
        @if ($branding->logo === null)
            <span class="{{ $s('brand.name') }}">{{ $branding->name }}</span>
        @else
            <span class="{{ $s('sr') }}">{{ $branding->name }}</span>
        @endif
        @if ($context->panelTitle() !== null)
            <span class="{{ $s($inverse ? 'brand.panel.inverse' : 'brand.panel') }}">{{ $context->panelTitle() }}</span>
        @endif
    </span>
</div>
