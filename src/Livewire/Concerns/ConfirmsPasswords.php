<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire\Concerns;

use Gcego\Sentinel\Security\StepUp;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Inline password confirmation (step-up) for sensitive actions, sharing
 * Laravel's confirmation timestamp (session "auth.password_confirmed_at",
 * the one the password.confirm middleware and Sentinel's confirm endpoint
 * use). The password is checked by the guard's user provider.
 *
 * Every sensitive action calls ensurePasswordIsConfirmed() itself: the
 * confirmation state lives on the server, never in the browser. The typed
 * password is cleared before every response, so it is never part of a
 * Livewire snapshot. Attempts are rate limited per user.
 */
trait ConfirmsPasswords
{
    public bool $confirmingPassword = false;

    #[Locked]
    public ?string $confirmingAction = null;

    #[Locked]
    public ?string $confirmingArgument = null;

    public string $confirmablePassword = '';

    /**
     * The methods confirmation may continue into.
     *
     * @return list<string>
     */
    abstract protected function confirmableActions(): array;

    /**
     * Run $action now when the password was confirmed recently, otherwise ask for it first.
     */
    public function startConfirmingPassword(string $action, ?string $argument = null): mixed
    {
        if (! in_array($action, $this->confirmableActions(), true)) {
            throw new AccessDeniedHttpException;
        }

        if ($this->passwordIsConfirmed()) {
            return $this->runConfirmed($action, $argument);
        }

        $this->resetErrorBag('confirmablePassword');
        $this->confirmingPassword = true;
        $this->confirmingAction = $action;
        $this->confirmingArgument = $argument;
        $this->dispatch('gcego-sentinel-confirming-password');

        return null;
    }

    public function confirmPassword(): mixed
    {
        $user = $this->account();
        $key = 'gcego-sentinel:confirm-password:'.sha1((string) UserKey::of($user));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->confirmablePassword = '';

            throw ValidationException::withMessages(['confirmablePassword' => [__('gcego-sentinel::messages.lockout', ['seconds' => RateLimiter::availableIn($key)])]]);
        }

        $password = $this->confirmablePassword;
        $this->confirmablePassword = '';
        $confirmed = $password !== '' && strlen($password) <= 4096 && app(SentinelManager::class)->provider()->validateCredentials($user, ['password' => $password]);

        if (! $confirmed) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['confirmablePassword' => [__('gcego-sentinel::messages.confirm.failed')]]);
        }

        RateLimiter::clear($key);
        app(StepUp::class)->confirm($this->currentSession());
        $action = $this->confirmingAction;
        $argument = $this->confirmingArgument;
        $this->stopConfirmingPassword();

        if ($action === null || ! in_array($action, $this->confirmableActions(), true)) {
            return null;
        }

        return $this->runConfirmed($action, $argument);
    }

    public function stopConfirmingPassword(): void
    {
        $this->confirmingPassword = false;
        $this->confirmingAction = null;
        $this->confirmingArgument = null;
        $this->confirmablePassword = '';
    }

    public function dehydrateConfirmsPasswords(): void
    {
        $this->confirmablePassword = '';
    }

    /**
     * Run an allowed action with its dependencies injected, like Livewire does.
     */
    private function runConfirmed(string $action, ?string $argument): mixed
    {
        $method = new ReflectionMethod($this, $action);
        $parameters = [];

        foreach ($argument === null ? [] : $method->getParameters() as $parameter) {
            $type = $parameter->getType();

            // The browser's argument fills the first scalar parameter; services are injected.
            if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                $parameters[$parameter->getName()] = $argument;

                break;
            }
        }

        return app()->call($method->getClosure($this), $parameters);
    }

    protected function passwordIsConfirmed(): bool
    {
        return app(StepUp::class)->confirmed($this->currentSession());
    }

    /**
     * @throws AccessDeniedHttpException
     */
    protected function ensurePasswordIsConfirmed(): void
    {
        if (! $this->passwordIsConfirmed()) {
            throw new AccessDeniedHttpException(__('gcego-sentinel::messages.confirm.required'));
        }
    }
}
