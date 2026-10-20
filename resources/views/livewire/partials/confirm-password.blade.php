@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<div class="{{ $s('confirm') }}" role="group" aria-labelledby="{{ $id }}-title">
    <div>
        <p id="{{ $id }}-title" class="text-sm font-semibold text-gcego-text">{{ __('gcego-sentinel::messages.confirm.heading') }}</p>
        <p class="{{ $s('help') }}">{{ __('gcego-sentinel::messages.confirm.inline') }}</p>
    </div>
    <x-gcego-sentinel::password name="confirmablePassword" :id="$id" :label="__('gcego-sentinel::messages.fields.password')" model="confirmablePassword" autocomplete="current-password" :autofocus="true" wire:keydown.enter.prevent="confirmPassword" />
    <div class="{{ $s('actions.row') }}">
        <x-gcego-sentinel::button type="button" variant="ghost" :block="false" wire:click="stopConfirmingPassword">{{ __('gcego-sentinel::messages.cancel') }}</x-gcego-sentinel::button>
        <x-gcego-sentinel::button type="button" :block="false" loading="confirmPassword" wire:click="confirmPassword" wire:loading.attr="disabled">{{ __('gcego-sentinel::messages.confirm.submit') }}</x-gcego-sentinel::button>
    </div>
</div>
