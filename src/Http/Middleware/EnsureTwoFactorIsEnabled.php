<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Middleware;

use Closure;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\Redirect;
use Gcego\Sentinel\Support\Routes;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

/**
 * sentinel.two-factor[:panel]: users who must use two-factor
 * authentication (the panel's "two_factor" flag, or a permission in
 * gcego-sentinel.two_factor.required_permissions)
 *
 *  - cannot continue until they have enrolled: they are sent to the account
 *    security page, and come back here afterwards;
 *  - must be in a session that proved the second factor: a session signed
 *    in with the password alone (from before the requirement, say) is
 *    signed out and has to sign in again.
 *
 * Put it on the panel's routes (gcego/studio adds it to every panel):
 *
 *     Route::middleware(['auth', 'sentinel.two-factor:admin'])->prefix('admin')->group(...);
 */
final readonly class EnsureTwoFactorIsEnabled
{
    public function __construct(
        private SentinelManager $sentinel,
        private TwoFactorPolicy $policy,
        private AuthenticationPanels $panels,
        private UrlGenerator $urls,
    ) {}

    public function handle(Request $request, Closure $next, ?string $panel = null): mixed
    {
        $user = $this->sentinel->guard()->user();
        $panel = $panel === null ? null : $this->panels->find($panel);

        if ($user === null || $request->routeIs(SentinelRoutes::AccountSecurity, 'logout', 'password.confirm', 'password.confirm.store', 'two-factor.*', 'passkey.*') || ! $this->policy->required($user, $panel)) {
            return $next($request);
        }

        if (! $this->policy->missing($user, $panel)) {
            if (! $request->hasSession() || $this->policy->sessionIsMultiFactor($request->session())) {
                return $next($request);
            }

            return $this->reauthenticate($request);
        }

        if ($request->expectsJson() || ! Routes::has(SentinelRoutes::AccountSecurity)) {
            return new JsonResponse(['message' => __('gcego-sentinel::messages.two_factor.required')], 403);
        }

        if ($request->isMethod('GET') && $request->hasSession()) {
            $request->session()->put(SessionKeys::Continue, $request->getRequestUri());
        }

        return Redirect::to($this->urls->route(SentinelRoutes::AccountSecurity), $request)->with('status', 'two-factor-required');
    }

    private function reauthenticate(Request $request): mixed
    {
        $guard = $this->sentinel->guard();
        $guard instanceof SessionGuard ? $guard->logoutCurrentDevice() : $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $message = __('gcego-sentinel::messages.two_factor.reauthenticate');

        if ($request->expectsJson() || ! Routes::has('login')) {
            return new JsonResponse(['message' => $message], 401);
        }

        return Redirect::to($this->urls->route('login'), $request)->withErrors([$this->sentinel->config()->identifier => $message]);
    }
}
