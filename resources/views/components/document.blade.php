@props(['context', 'title' => null])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $assets = app(\Gcego\Sentinel\Support\Assets::class);
    $styling = app(\Gcego\Chroma\ChromaManager::class);
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $brandCss = $context->branding->css();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $context->theme === 'dark']) data-sentinel-theme="{{ $context->theme }}" data-sentinel-storage-key="{{ $styling->storageKey() }}" data-sentinel-switching="{{ $styling->allowsSwitching() ? 'true' : 'false' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $context->theme === 'system' ? 'light dark' : $context->theme }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $context->title() }} · {{ $context->branding->name }}</title>
    @if ($context->branding->favicon !== null)
        <link rel="icon" href="{{ $context->branding->favicon }}">
    @endif
    {{ $assets->script() }}
    {{ $assets->stylesheet() }}
    @if ($brandCss !== '')
        <style data-sentinel-brand @if ($nonce) nonce="{{ $nonce }}" @endif>{!! $brandCss !!}</style>
    @endif
    @stack('gcego-sentinel-head')
</head>
<body class="{{ $s('document') }}">
    <a href="#sentinel-main" class="{{ $s('skip') }}">{{ __('gcego-sentinel::messages.skip') }}</a>
    {{ $slot }}
    <div class="{{ $s('live') }}" aria-live="polite" aria-atomic="true" data-sentinel-live></div>
</body>
</html>
