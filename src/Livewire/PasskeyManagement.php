<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire;

use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Livewire\Concerns\ConfirmsPasswords;
use Gcego\Sentinel\Livewire\Concerns\InteractsWithAccount;
use Gcego\Sentinel\Passkeys\PasskeyAuthentication;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The user's passkeys: list, add and remove. Adding one first confirms the
 * password here, then Sentinel's script runs the WebAuthn ceremony against
 * the passkey endpoints (which check the confirmation again). Removing one
 * needs a recent password confirmation, and the last passkey cannot be
 * removed while it is the user's only required second factor.
 */
final class PasskeyManagement extends Component
{
    use ConfirmsPasswords;
    use InteractsWithAccount;

    public string $name = '';

    public bool $registering = false;

    public ?string $notice = null;

    public function mount(): void
    {
        if (! app(SentinelManager::class)->enabled(Feature::Passkeys)) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * Confirmed: hand the ceremony to the browser.
     */
    public function prepareRegistration(): void
    {
        $this->ensurePasswordIsConfirmed();
        $this->resetErrorBag('name');
        $this->validate(['name' => ['required', 'string', 'max:60']]);
        $this->registering = true;
        $this->dispatch('gcego-sentinel-passkey-register', name: trim($this->name));
    }

    #[On('gcego-sentinel-passkey-registered')]
    public function registered(): void
    {
        $this->registering = false;
        $this->name = '';
        $this->notice = __('gcego-sentinel::messages.passkeys.added');
    }

    #[On('gcego-sentinel-passkey-failed')]
    public function failed(): void
    {
        $this->registering = false;
    }

    public function removePasskey(string $id): void
    {
        $this->ensurePasswordIsConfirmed();
        $user = $this->account();
        $passkeys = app(PasskeyAuthentication::class);
        $policy = app(TwoFactorPolicy::class);

        if ($passkeys->count($user) === 1 && $policy->required($user) && ! $policy->enrolled($user)) {
            $this->addError('passkey', __('gcego-sentinel::messages.passkeys.last_required'));

            return;
        }

        if ($passkeys->remove($user, $id)) {
            $this->notice = __('gcego-sentinel::messages.passkeys.removed');
        }
    }

    public function render(): View
    {
        return view('gcego-sentinel::livewire.passkeys', [
            'passkeys' => app(PasskeyAuthentication::class)->list($this->account()),
        ]);
    }

    protected function confirmableActions(): array
    {
        return ['prepareRegistration', 'removePasskey'];
    }
}
