<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire;

use Gcego\Sentinel\Events\BrowserSessionsRevoked;
use Gcego\Sentinel\Livewire\Concerns\ConfirmsPasswords;
use Gcego\Sentinel\Livewire\Concerns\InteractsWithAccount;
use Gcego\Sentinel\Security\StepUp;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The user's browser sessions (database session driver only) and
 * signing out of the others, server-side, after the password is confirmed:
 * the account's session epoch moves on (ending every other session on any
 * driver, remember-me logins included) and the other stored sessions are
 * deleted. The current session is kept.
 */
final class BrowserSessions extends Component
{
    use ConfirmsPasswords;
    use InteractsWithAccount;

    public string $password = '';

    public bool $signedOut = false;

    public function mount(): void
    {
        if (! app(SentinelConfig::class)->browserSessions) {
            throw new NotFoundHttpException;
        }
    }

    public function logoutOtherBrowserSessions(SessionRepository $sessions): void
    {
        $this->resetErrorBag();
        $this->signedOut = false;
        $user = $this->account();
        $sentinel = app(SentinelManager::class);
        $guard = $sentinel->guard();
        $key = 'gcego-sentinel:logout-others:'.sha1((string) UserKey::of($user));
        $password = $this->password;
        $this->password = '';

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => [__('gcego-sentinel::messages.lockout', ['seconds' => RateLimiter::availableIn($key)])]]);
        }

        if ($password === '' || strlen($password) > 4096 || ! $sentinel->provider()->validateCredentials($user, ['password' => $password])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['password' => [__('gcego-sentinel::messages.confirm.failed')]]);
        }

        RateLimiter::clear($key);
        app(StepUp::class)->confirm($this->currentSession());
        $security = app(SessionSecurity::class);
        $security->revokeAll($user);
        $security->acknowledge($this->currentSession(), $user);
        $count = $sessions->deleteOthers($user, session()->getId());

        event(new BrowserSessionsRevoked(UserKey::of($user), $sentinel->guardName(), $count, true));
        $this->signedOut = true;
    }

    public function revokeSession(string $id): void
    {
        $this->ensurePasswordIsConfirmed();
        $user = $this->account();

        if (app(SessionRepository::class)->delete($user, $id, session()->getId())) {
            event(new BrowserSessionsRevoked(UserKey::of($user), app(SentinelManager::class)->guardName(), 1, false));
        }
    }

    public function dehydrate(): void
    {
        $this->password = '';
    }

    public function render(): View
    {
        $sessions = app(SessionRepository::class);

        return view('gcego-sentinel::livewire.browser-sessions', [
            'supported' => $sessions->supported(),
            'sessions' => $sessions->forUser($this->account(), session()->getId()),
        ]);
    }

    protected function confirmableActions(): array
    {
        return ['revokeSession'];
    }
}
