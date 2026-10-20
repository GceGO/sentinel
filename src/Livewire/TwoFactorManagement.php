<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire;

use Gcego\Sentinel\Livewire\Concerns\ConfirmsPasswords;
use Gcego\Sentinel\Livewire\Concerns\InteractsWithAccount;
use Gcego\Sentinel\MultiFactor\TwoFactorAuthentication;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Two-factor enrollment and recovery codes through Sentinel's
 * TwoFactorAuthentication (which fires the security events and notices).
 *
 * Secrets never enter component state: the QR code and setup key are
 * rendered only while setup is pending, and recovery codes only in the
 * response to the action that created them (they are stored hashed and can
 * never be shown again); none of them is a public property, so no snapshot
 * contains them. Starting setup, regenerating codes and turning
 * two-factor off always need a recent password confirmation. Confirming
 * the setup with a code also upgrades the current session to multi-factor.
 */
final class TwoFactorManagement extends Component
{
    use ConfirmsPasswords;
    use InteractsWithAccount;

    private const string SetupKey = 'gcego-sentinel.two-factor-setup';

    public string $code = '';

    public bool $justConfirmed = false;

    /**
     * Recovery codes created by this request's action; never serialized.
     *
     * @var list<string>
     */
    protected array $revealedCodes = [];

    public function mount(): void
    {
        if (! app(SentinelConfig::class)->twoFactor) {
            throw new NotFoundHttpException;
        }

        // An abandoned setup (secret generated, never confirmed) starts over.
        if ($this->twoFactor()->pending($this->account()) && ! session()->has(self::SetupKey)) {
            $this->twoFactor()->disable($this->account());
        }
    }

    public function enableTwoFactorAuthentication(): void
    {
        $this->ensurePasswordIsConfirmed();

        if ($this->twoFactor()->enrolled($this->account())) {
            return;
        }

        $this->twoFactor()->begin($this->account());
        session()->put(self::SetupKey, true);
        $this->justConfirmed = false;
    }

    public function confirmTwoFactorAuthentication(): void
    {
        $this->resetErrorBag('code');
        $code = preg_replace('/\D/', '', $this->code) ?? '';
        $this->code = '';
        $codes = $this->twoFactor()->confirm($this->account(), $code);

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => [__('gcego-sentinel::messages.two_factor.invalid_code')]]);
        }

        session()->forget(self::SetupKey);
        app(TwoFactorPolicy::class)->markSession($this->currentSession(), true);
        $this->justConfirmed = true;
        $this->revealedCodes = $codes;
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->ensurePasswordIsConfirmed();
        $this->revealedCodes = $this->twoFactor()->regenerateRecoveryCodes($this->account());
    }

    public function disableTwoFactorAuthentication(): void
    {
        $this->ensurePasswordIsConfirmed();
        $user = $this->account();

        if ($this->twoFactor()->enrolled($user) && app(TwoFactorPolicy::class)->required($user)) {
            $this->addError('two_factor', __('gcego-sentinel::messages.two_factor.cannot_disable'));

            return;
        }

        $this->twoFactor()->disable($user);
        session()->forget(self::SetupKey);
        $this->justConfirmed = false;
        $this->revealedCodes = [];
    }

    public function dehydrate(): void
    {
        $this->code = '';
    }

    public function render(): View
    {
        $user = $this->account();
        $twoFactor = $this->twoFactor();
        $enabled = $twoFactor->enrolled($user);

        return view('gcego-sentinel::livewire.two-factor', [
            'enabled' => $enabled,
            'pending' => $twoFactor->pending($user),
            'qrCode' => $twoFactor->qrCodeSvg($user),
            'setupKey' => $twoFactor->setupKey($user),
            'recoveryCodes' => $this->revealedCodes,
            'remaining' => $enabled ? $twoFactor->remainingRecoveryCodes($user) : null,
            'required' => app(TwoFactorPolicy::class)->required($user),
        ]);
    }

    protected function confirmableActions(): array
    {
        return ['enableTwoFactorAuthentication', 'regenerateRecoveryCodes', 'disableTwoFactorAuthentication'];
    }

    private function twoFactor(): TwoFactorAuthentication
    {
        return app(TwoFactorAuthentication::class);
    }
}
