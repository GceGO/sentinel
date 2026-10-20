<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Accounts;

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\AccountStateResolver;
use Gcego\Sentinel\Events\AccountAccessDenied;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\Routes;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\Events\Validated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement of account states, at three points:
 *
 *  1. while signing in (once the password or passkey is known to be
 *     right): the attempt fails with a generic message before a session exists;
 *  2. when the sign-in completes (also after a two-factor challenge):
 *     the new session is ended;
 *  3. on every request through the sentinel.active middleware (remember-me
 *     logins, accounts suspended mid-session): the session is ended.
 *
 * The message never says why beyond "unavailable", and is only given to
 * someone who proved the password, so it reveals nothing about other accounts.
 */
final readonly class AccountAccess
{
    public function __construct(
        private AccountStateResolver $states,
        private SecurityHolds $holds,
        private SentinelConfig $config,
        private Dispatcher $events,
        private UrlGenerator $urls,
        private AuthFactory $auth,
    ) {}

    public function state(Authenticatable $user): AccountState
    {
        $state = $this->states->state($user);

        if ($state === AccountState::Active && $this->holds->active($user) !== null) {
            return AccountState::Held;
        }

        return $state;
    }

    /**
     * Refuse a sign-in to an inactive account (after the credentials proved right).
     *
     * @throws ValidationException
     */
    public function handleValidated(Validated $event): void
    {
        $state = $this->state($event->user);

        if ($state->allowsAccess()) {
            return;
        }

        $this->events->dispatch(new AccountAccessDenied(UserKey::of($event->user), $state, $event->guard, 'login'));

        throw ValidationException::withMessages([
            $this->config->identifier => [__('gcego-sentinel::messages.account.unavailable_login')],
        ]);
    }

    /**
     * End the session of an inactive account and answer with the account-unavailable page.
     */
    public function endSession(Request $request, string $guard, Authenticatable $user, AccountState $state, string $stage): Response
    {
        $this->events->dispatch(new AccountAccessDenied(UserKey::of($user), $state, $guard, $stage));
        $session = $this->auth->guard($guard);

        if ($session instanceof StatefulGuard) {
            $session->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash(SessionKeys::AccountState, $state->value);
        }

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => __('gcego-sentinel::messages.account.unavailable_login')], 403);
        }

        $url = Routes::has(SentinelRoutes::AccountUnavailable) ? $this->urls->route(SentinelRoutes::AccountUnavailable) : '/';

        return new RedirectResponse($url);
    }
}
