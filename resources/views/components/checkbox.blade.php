@props(['name', 'checked' => false, 'required' => false, 'id' => null, 'error' => null])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $id ??= 'sentinel-'.str_replace(['.', '_'], '-', $name);
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $message = $errorBag->first($error ?? $name);
@endphp
<div class="{{ $s('field') }}">
    <div class="{{ $s('checkbox.row') }}">
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="1" @checked($checked) @if ($required) required aria-required="true" @endif @if ($message !== '') aria-invalid="true" aria-describedby="{{ $id }}-error" @endif class="{{ $s('checkbox') }}">
        <label for="{{ $id }}" class="{{ $s('checkbox.label') }}">{{ $slot }}</label>
    </div>
    @if ($message !== '')
        <x-gcego-sentinel::field-error :id="$id.'-error'" :message="$message" />
    @endif
</div>
