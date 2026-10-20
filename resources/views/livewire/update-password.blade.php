@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<section aria-labelledby="sentinel-password-title" class="{{ $s('section') }}">
    <x-gcego-sentinel::section-header id="sentinel-password-title" icon="lock" :title="__('gcego-sentinel::messages.password_update.heading')" :description="__('gcego-sentinel::messages.password_update.description')" />
    <form wire:submit="updatePassword" class="{{ $s('section.body') }}">
        <x-gcego-sentinel::password name="current_password" id="sentinel-current-password" :label="__('gcego-sentinel::messages.fields.current_password')" model="state.current_password" autocomplete="current-password" />
        <div class="{{ $s('section.grid') }}">
            <x-gcego-sentinel::password name="password" id="sentinel-new-password" :label="__('gcego-sentinel::messages.fields.new_password')" model="state.password" autocomplete="new-password" :guidance="true" />
            <x-gcego-sentinel::password name="password_confirmation" id="sentinel-new-password-confirmation" :label="__('gcego-sentinel::messages.fields.password_confirmation')" model="state.password_confirmation" autocomplete="new-password" />
        </div>
        <div class="{{ $s('actions.row') }}">
            <div role="status" class="text-sm">
                @if ($saved)
                    <span class="{{ $s('saved') }}">{{ __('gcego-sentinel::messages.password_update.saved') }}</span>
                @endif
            </div>
            <x-gcego-sentinel::button :block="false" loading="updatePassword" wire:loading.attr="disabled">{{ __('gcego-sentinel::messages.password_update.submit') }}</x-gcego-sentinel::button>
        </div>
    </form>
</section>
