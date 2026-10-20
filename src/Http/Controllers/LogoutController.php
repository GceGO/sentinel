<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Events\LogoutCompleted;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /logout: signs out (the guard cycles the remember-me token),
 * invalidates the session and issues a new CSRF token. "everywhere=1" also
 * ends every other session of the account. Safe to repeat: a visitor who is
 * already signed out simply gets the signed-out page.
 */
final readonly class LogoutController
{
    public function __construct(
        private SentinelManager $sentinel,
        private AuthenticationRedirectResolver $redirects,
        private SessionRepository $sessions,
        private SessionSecurity $sessionSecurity,
        private Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $guard = $this->sentinel->guard();
        $user = $guard->user();
        $everywhere = $user !== null && $request->boolean('everywhere');
        $destination = $this->redirects->afterLogout($request);

        if ($everywhere) {
            $this->sessions->deleteAll($user);
            $this->sessionSecurity->revokeAll($user);
        }

        if ($user !== null) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user !== null) {
            $this->events->dispatch(new LogoutCompleted(UserKey::of($user), $this->sentinel->guardName(), $everywhere));
        }

        return $request->wantsJson() ? new JsonResponse('', 204) : new RedirectResponse($destination);
    }
}
