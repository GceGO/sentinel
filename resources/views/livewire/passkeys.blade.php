@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<section aria-labelledby="sentinel-passkeys-title" class="{{ $s('section') }}"
    data-sentinel-passkey-register
    data-options-url="{{ route('passkey.registration-options') }}"
    data-store-url="{{ route('passkey.store') }}"
    data-unsupported="{{ __('gcego-sentinel::messages.passkeys.browser_unsupported') }}"
    data-failed="{{ __('gcego-sentinel::messages.passkeys.failed') }}">
    <x-gcego-sentinel::section-header id="sentinel-passkeys-title" icon="authentication.passkey" :title="__('gcego-sentinel::messages.passkeys.heading')" :description="__('gcego-sentinel::messages.passkeys.description')" />
    <div class="{{ $s('section.body') }}">
        @error('passkey')
            <x-gcego-sentinel::status tone="danger" :message="$message" />
        @enderror
        @if ($notice !== null)
            <x-gcego-sentinel::status :message="$notice" />
        @endif
        <p class="text-sm" role="status" aria-live="polite" data-sentinel-passkey-status></p>

        @if ($passkeys->isNotEmpty())
            <ul class="{{ $s('sessions') }}" aria-label="{{ __('gcego-sentinel::messages.passkeys.list') }}">
                @foreach ($passkeys as $passkey)
                    <li class="{{ $s('session') }}" wire:key="passkey-{{ $passkey->getKey() }}">
                        <span class="{{ $s('session.icon') }}"><x-gcego-sentinel::icon name="key" class="size-5" /></span>
                        <div class="{{ $s('session.text') }}">
                            <span class="{{ $s('session.name') }}">{{ $passkey->name }}</span>
                            <span class="{{ $s('session.meta') }}">
                                @if ($passkey->authenticator !== null)
                                    {{ $passkey->authenticator }} ·
                                @endif
                                {{ $passkey->last_used_at !== null ? __('gcego-sentinel::messages.passkeys.last_used', ['time' => $passkey->last_used_at->locale(app()->getLocale())->diffForHumans()]) : __('gcego-sentinel::messages.passkeys.never_used') }}
                            </span>
                        </div>
                        <x-gcego-sentinel::button type="button" variant="ghost" :block="false" :small="true" wire:click="startConfirmingPassword('removePasskey', '{{ $passkey->getKey() }}')" :aria-label="__('gcego-sentinel::messages.passkeys.remove_label', ['name' => $passkey->name])">{{ __('gcego-sentinel::messages.passkeys.remove') }}</x-gcego-sentinel::button>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gcego-text">{{ __('gcego-sentinel::messages.passkeys.none') }}</p>
        @endif

        @if ($confirmingPassword)
            @include('gcego-sentinel::livewire.partials.confirm-password', ['id' => 'sentinel-passkeys-confirm'])
        @endif

        <form wire:submit="startConfirmingPassword('prepareRegistration')" class="flex flex-col gap-4 border-t border-gcego-border pt-5 sm:max-w-sm">
            <x-gcego-sentinel::input name="name" id="sentinel-passkey-name" :label="__('gcego-sentinel::messages.passkeys.name')" model="name" autocomplete="off" maxlength="60" :required="true" :help="__('gcego-sentinel::messages.passkeys.name_help')" />
            <div>
                <x-gcego-sentinel::button :block="false" icon="key" loading="prepareRegistration,startConfirmingPassword" wire:loading.attr="disabled" :disabled="$registering">{{ __('gcego-sentinel::messages.passkeys.add') }}</x-gcego-sentinel::button>
            </div>
        </form>
    </div>
</section>
