<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Middleware;

use Closure;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Events\SessionExpired;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\Routes;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Date;

/**
 * sentinel.session (added to the "web" group): ends a signed-in session
 *
 *  - after sessions.idle_timeout minutes without a request;
 *  - sessions.absolute_timeout minutes after it signed in, whatever the activity;
 *  - when the account's password changed since it signed in
 *    (sessions.bind_to_password), on any session driver;
 *  - when the account's session epoch moved on (sign out everywhere, password
 *    reset, security hold).
 *
 * Sessions restored from a remember-me cookie start their clock on their
 * first request. Ended sessions go to the session-expired page.
 */
final readonly class EnforceSessionSecurity
{
    public function __construct(
        private SentinelManager $sentinel,
        private SentinelConfig $config,
        private SessionSecurity $sessions,
        private Dispatcher $events,
        private UrlGenerator $urls,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $guard = $this->sentinel->guard();
        $user = $guard->user();

        if ($user === null) {
            return $next($request);
        }

        $session = $request->session();
        $now = Date::now()->getTimestamp();
        $authenticatedAt = $session->get(SessionKeys::AuthenticatedAt);
        $lastActivity = $session->get(SessionKeys::LastActivity);

        if (! is_int($authenticatedAt)) {
            // Signed in by a remember-me cookie, or before Sentinel tracked the session.
            $session->put(SessionKeys::AuthenticatedAt, $authenticatedAt = $now);
            $this->sessions->rebind($session, $user);
            $this->sessions->acknowledge($session, $user);
        }

        $reason = match (true) {
            $this->config->idleTimeout !== null && is_int($lastActivity) && $now - $lastActivity > $this->config->idleTimeout * 60 => 'idle',
            $this->config->absoluteTimeout !== null && $now - $authenticatedAt > $this->config->absoluteTimeout * 60 => 'absolute',
            $this->config->bindSessionsToPassword && ! $this->sessions->matches($session, $user) => 'password_changed',
            $this->sessions->revoked($session, $user) => 'revoked',
            default => null,
        };

        if ($reason !== null) {
            // This device only: logout() would also cycle the remember-me token of every other device.
            $guard instanceof SessionGuard ? $guard->logoutCurrentDevice() : $guard->logout();
            $session->invalidate();
            $session->regenerateToken();
            $this->events->dispatch(new SessionExpired(UserKey::of($user), $this->sentinel->guardName(), $reason));

            if ($request->expectsJson()) {
                return new JsonResponse(['message' => __('gcego-sentinel::messages.expired.signed_out')], 401);
            }

            return new RedirectResponse(Routes::has(SentinelRoutes::SessionExpired) ? $this->urls->route(SentinelRoutes::SessionExpired) : '/');
        }

        $session->put(SessionKeys::LastActivity, $now);

        return $next($request);
    }
}
