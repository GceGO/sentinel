@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    // Guided setup: 1 turn on, 2 scan, 3 confirm, 4 save codes. Driven by the component's real state only.
    $step = $justConfirmed && $recoveryCodes !== [] ? 4 : ($pending ? 2 : ($enabled ? null : 1));
    $steps = ['start', 'scan', 'confirm', 'save'];
@endphp
<section aria-labelledby="sentinel-two-factor-title" class="{{ $s('section') }}">
    <x-gcego-sentinel::section-header id="sentinel-two-factor-title" icon="authentication.mfa" :title="__('gcego-sentinel::messages.two_factor.heading')" :description="__('gcego-sentinel::messages.two_factor.description')">
        @if ($enabled)
            <x-gcego-sentinel::badge tone="success">{{ __('gcego-sentinel::messages.security.enabled') }}</x-gcego-sentinel::badge>
        @elseif ($pending)
            <x-gcego-sentinel::badge tone="warning">{{ __('gcego-sentinel::messages.two_factor.pending') }}</x-gcego-sentinel::badge>
        @else
            <x-gcego-sentinel::badge>{{ __('gcego-sentinel::messages.security.disabled') }}</x-gcego-sentinel::badge>
        @endif
    </x-gcego-sentinel::section-header>
    <div class="{{ $s('section.body') }}">
        @if ($step !== null)
            <ol class="{{ $s('steps') }}" aria-label="{{ __('gcego-sentinel::messages.mfa_steps.label') }}">
                @foreach ($steps as $index => $name)
                    @php($number = $index + 1)
                    @php($state = $number < $step || ($number === 3 && $step === 4) ? 'done' : (($number === $step || ($step === 2 && $number === 3)) ? 'current' : 'upcoming'))
                    <li class="{{ $state === 'upcoming' ? $s('step') : $s('step', 'step.'.$state) }}" @if ($number === $step) aria-current="step" @endif>
                        <span class="{{ $state === 'upcoming' ? $s('step.number') : $s('step.number', 'step.number.'.$state) }}" aria-hidden="true">
                            @if ($state === 'done')
                                <x-gcego-sentinel::icon name="check" class="size-3.5" />
                            @else
                                {{ $number }}
                            @endif
                        </span>
                        <span>{{ __('gcego-sentinel::messages.mfa_steps.'.$name) }}</span>
                    </li>
                    @if (! $loop->last)
                        <li class="{{ $s('step.line') }}" aria-hidden="true"></li>
                    @endif
                @endforeach
            </ol>
        @endif

        @error('two_factor')
            <x-gcego-sentinel::status tone="danger" :message="$message" />
        @enderror

        @if ($justConfirmed)
            <x-gcego-sentinel::status :message="__('gcego-sentinel::messages.status.two-factor-authentication-confirmed')" />
        @endif

        @if ($required && ! $enabled)
            <x-gcego-sentinel::status tone="warning" :message="__('gcego-sentinel::messages.two_factor.required')" />
        @endif

        <p class="text-sm leading-6 text-gcego-text">
            @if ($enabled)
                {{ __('gcego-sentinel::messages.two_factor.enabled_text') }}
            @elseif ($pending)
                {{ __('gcego-sentinel::messages.two_factor.pending_text') }}
            @else
                {{ __('gcego-sentinel::messages.two_factor.disabled_text') }}
            @endif
        </p>

        @if ($qrCode !== null)
            <x-gcego-sentinel::qr-code :svg="$qrCode" :setup-key="$setupKey" />
        @endif

        @if ($pending)
            <form wire:submit="confirmTwoFactorAuthentication" class="flex flex-col gap-4 border-t border-gcego-border pt-5 sm:max-w-xs">
                <x-gcego-sentinel::input name="code" id="sentinel-two-factor-code" :label="__('gcego-sentinel::messages.fields.code')" model="code" autocomplete="one-time-code" inputmode="numeric" maxlength="10" :code="true" placeholder="000000" :required="true" />
                <x-gcego-sentinel::button :block="false" loading="confirmTwoFactorAuthentication" wire:loading.attr="disabled">{{ __('gcego-sentinel::messages.two_factor.confirm') }}</x-gcego-sentinel::button>
            </form>
        @endif

        @if ($recoveryCodes !== [])
            <x-gcego-sentinel::status tone="warning" :message="__('gcego-sentinel::messages.two_factor.store_codes')" />
            <x-gcego-sentinel::recovery-codes :codes="$recoveryCodes" />
        @elseif ($remaining !== null)
            <p class="{{ $s('help') }} flex items-center gap-2" data-sentinel-recovery-remaining>
                <x-gcego-sentinel::icon name="authentication.recovery-codes" class="size-4 shrink-0" />
                {{ trans_choice('gcego-sentinel::messages.two_factor.remaining', $remaining, ['count' => $remaining]) }}
            </p>
            @if ($remaining <= 2)
                <x-gcego-sentinel::status tone="warning" :message="__('gcego-sentinel::messages.two_factor.running_low')" />
            @endif
        @endif

        @if ($confirmingPassword)
            @include('gcego-sentinel::livewire.partials.confirm-password', ['id' => 'sentinel-two-factor-confirm'])
        @endif

        <div class="flex flex-wrap gap-3">
            @if (! $enabled && ! $pending)
                <x-gcego-sentinel::button type="button" :block="false" icon="shield" loading="enableTwoFactorAuthentication,startConfirmingPassword" wire:loading.attr="disabled" wire:click="startConfirmingPassword('enableTwoFactorAuthentication')">{{ __('gcego-sentinel::messages.two_factor.enable') }}</x-gcego-sentinel::button>
            @endif
            @if ($enabled)
                <x-gcego-sentinel::button type="button" variant="secondary" :block="false" icon="refresh" wire:click="startConfirmingPassword('regenerateRecoveryCodes')">{{ __('gcego-sentinel::messages.two_factor.regenerate') }}</x-gcego-sentinel::button>
            @endif
            @if ($enabled || $pending)
                <x-gcego-sentinel::button type="button" :variant="$pending ? 'ghost' : 'danger'" :block="false" wire:click="startConfirmingPassword('disableTwoFactorAuthentication')">{{ $pending ? __('gcego-sentinel::messages.cancel') : __('gcego-sentinel::messages.two_factor.disable') }}</x-gcego-sentinel::button>
            @endif
        </div>
    </div>
</section>
