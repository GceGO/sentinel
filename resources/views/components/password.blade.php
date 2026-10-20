@props([
    'name' => 'password',
    'label',
    'autocomplete' => 'current-password',
    'required' => true,
    'help' => null,
    'autofocus' => false,
    'error' => null,
    'model' => null,
    'id' => null,
    'guidance' => false,
    'action' => null,
    'icon' => 'lock',
])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $id ??= 'sentinel-'.str_replace(['.', '_'], '-', $name);
    $errorKey = $error ?? $name;
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $message = $errorBag->first($errorKey);
    $rules = $guidance ? \Gcego\Sentinel\Support\PasswordGuidance::rules() : null;
    $describedBy = trim(($help !== null ? $id.'-help ' : '').($guidance ? $id.'-rules ' : '').($message !== '' ? $id.'-error' : ''));
@endphp
<div class="{{ $s('field') }}">
    <div class="{{ $s('label.row') }}">
        <label for="{{ $id }}" class="{{ $s('label') }}">{{ $label }}@if ($required)<span class="{{ $s('required') }}" aria-hidden="true"> *</span>@endif</label>
        {{ $action }}
    </div>
    <div class="{{ $s('input.wrapper') }}">
        @if ($icon !== null)
            <span class="{{ $s('input.icon') }}"><x-gcego-sentinel::icon :name="$icon" :class="$s('input.icon.svg')" /></span>
        @endif
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            autocomplete="{{ $autocomplete }}"
            @if ($model !== null) wire:model="{{ $model }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($autofocus) autofocus @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($message !== '') aria-invalid="true" @endif
            @if ($guidance) data-sentinel-strength="{{ $id }}-strength" data-sentinel-rules="{{ $id }}-rules" data-min="{{ $rules['min'] }}" @endif
            spellcheck="false"
            autocapitalize="off"
            {{ $attributes->class([$s('input'), $s('input.password'), $s('input.leading') => $icon !== null]) }}
        >
        <button type="button" hidden data-sentinel-js data-sentinel-password-toggle aria-controls="{{ $id }}" aria-pressed="false" aria-label="{{ __('gcego-sentinel::messages.password.show') }}" data-show-label="{{ __('gcego-sentinel::messages.password.show') }}" data-hide-label="{{ __('gcego-sentinel::messages.password.hide') }}" class="{{ $s('toggle') }}">
            <x-gcego-sentinel::icon name="eye" :class="$s('toggle.icon')" data-sentinel-icon="show" />
            <x-gcego-sentinel::icon name="eye-off" :class="$s('toggle.icon').' hidden'" data-sentinel-icon="hide" />
        </button>
    </div>
    @if ($help !== null)
        <p id="{{ $id }}-help" class="{{ $s('help') }}">{{ $help }}</p>
    @endif
    @if ($guidance)
        <div class="{{ $s('strength') }}" hidden data-sentinel-js aria-hidden="true"><div id="{{ $id }}-strength" class="{{ $s('strength.bar') }}" data-level="0"></div></div>
        <ul id="{{ $id }}-rules" class="{{ $s('rules') }}" aria-label="{{ __('gcego-sentinel::messages.password_rules.title') }}">
            @foreach (\Gcego\Sentinel\Support\PasswordGuidance::lines() as $rule => $line)
                <li class="{{ $s('rule') }}" data-rule="{{ $rule }}"><span class="{{ $s('rule.dot') }}" aria-hidden="true"></span>{{ $line }}</li>
            @endforeach
        </ul>
    @endif
    @if ($message !== '')
        <x-gcego-sentinel::field-error :id="$id.'-error'" :message="$message" />
    @endif
</div>
