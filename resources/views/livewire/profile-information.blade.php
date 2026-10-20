@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<section aria-labelledby="sentinel-profile-form-title" class="{{ $s('section') }}">
    <x-gcego-sentinel::section-header id="sentinel-profile-form-title" icon="user" :title="__('gcego-sentinel::messages.profile.heading')" :description="__('gcego-sentinel::messages.profile.description')" />
    <form wire:submit="updateProfileInformation" class="{{ $s('section.body') }}">
        <div class="{{ $s('section.grid') }}">
            @foreach ($fields as $field)
                <x-gcego-sentinel::input :name="$field->name" :id="'sentinel-profile-'.$field->name" :type="$field->type" :label="$field->label()" :model="'state.'.$field->name" :autocomplete="$field->autocomplete" :required="$field->required" :help="$field->help()" />
            @endforeach
        </div>
        @if ($confirmingPassword)
            @include('gcego-sentinel::livewire.partials.confirm-password', ['id' => 'sentinel-profile-confirm'])
        @endif
        <div class="{{ $s('actions.row') }}">
            <div role="status" class="text-sm">
                @if ($saved)
                    <span class="{{ $s('saved') }}">{{ __('gcego-sentinel::messages.saved') }}</span>
                @endif
                @if ($verificationSent)
                    <span class="text-gcego-text-muted">{{ __('gcego-sentinel::messages.profile.verification_sent') }}</span>
                @endif
            </div>
            <x-gcego-sentinel::button :block="false" loading="updateProfileInformation" wire:loading.attr="disabled">{{ __('gcego-sentinel::messages.save') }}</x-gcego-sentinel::button>
        </div>
    </form>
</section>
