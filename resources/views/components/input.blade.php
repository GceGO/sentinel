@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'required' => false,
    'help' => null,
    'autofocus' => false,
    'inputmode' => null,
    'error' => null,
    'model' => null,
    'id' => null,
    'code' => false,
    'maxlength' => null,
    'icon' => null,
])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $id ??= 'sentinel-'.str_replace(['.', '_'], '-', $name);
    $errorKey = $error ?? $name;
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $message = $errorBag->first($errorKey);
    $describedBy = trim(($help !== null ? $id.'-help ' : '').($message !== '' ? $id.'-error' : ''));
@endphp
<div class="{{ $s('field') }}">
    <label for="{{ $id }}" class="{{ $s('label') }}">{{ $label }}@if ($required)<span class="{{ $s('required') }}" aria-hidden="true"> *</span>@endif</label>
    <div class="{{ $s('input.wrapper') }}">
    @if ($icon !== null)
        <span class="{{ $s('input.icon') }}"><x-gcego-sentinel::icon :name="$icon" :class="$s('input.icon.svg')" /></span>
    @endif
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($model !== null) wire:model="{{ $model }}" @elseif ($value !== null) value="{{ $value }}" @endif
        @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
        @if ($inputmode !== null) inputmode="{{ $inputmode }}" @endif
        @if ($maxlength !== null) maxlength="{{ $maxlength }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($autofocus) autofocus @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($message !== '') aria-invalid="true" @endif
        {{ $attributes->class([$s('input'), $s('input.code') => $code, $s('input.leading') => $icon !== null]) }}
    >
    </div>
    @if ($help !== null)
        <p id="{{ $id }}-help" class="{{ $s('help') }}">{{ $help }}</p>
    @endif
    @if ($message !== '')
        <x-gcego-sentinel::field-error :id="$id.'-error'" :message="$message" />
    @endif
</div>
