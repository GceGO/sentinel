<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Authentication;

use Gcego\Security\SecurityManager;
use Gcego\Sentinel\Accounts\AccountAccess;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Contracts\RiskAssessor;
use Gcego\Sentinel\Events\AccountAccessDenied;
use Gcego\Sentinel\Events\LoginFailed;
use Gcego\Sentinel\Events\LoginRejected;
use Gcego\Sentinel\Events\LoginSucceeded;
use Gcego\Sentinel\Events\SuspiciousSignIn;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\Redirects\Destination;
use Gcego\Sentinel\Risk\KnownBrowsers;
use Gcego\Sentinel\Risk\RiskAssessment;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Sessions\UserAgent;
use Gcego\Sentinel\Support\Redirect;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The one way into a signed-in session. Every sign-in method (password,
 * two-factor challenge, recovery code, passkey) ends here, so none of them
 * can skip a step:
 *
 *  1. the account must be active (suspended, disabled, under review or on
 *     security hold: refused before any session exists);
 *  2. a password sign-in of a user with an authenticator waits for the
 *     code (PendingLogin) without being authenticated; a user whose
 *     required second factor is a passkey must sign in with it;
 *  3. the destination is resolved: a user who may not enter the requested
 *     panel or tenant is refused, and no session is created;
 *  4. only then the guard signs in, the session id is regenerated (no
 *     fixation), the session's security state is recorded, the tenant is
 *     switched through gcego/security, and the sign-in is audited, scored
 *     and, when the browser is new, notified.
 *
 * Users who must use two-factor authentication never get a remember-me
 * cookie, so a cookie can never stand in for their second factor.
 */
final readonly class LoginPipeline
{
    public function __construct(
        private SentinelManager $sentinel,
        private SentinelConfig $config,
        private AuthenticationRedirectResolver $redirects,
        private AccountAccess $accounts,
        private TwoFactorPolicy $twoFactor,
        private SessionSecurity $sessions,
        private SecurityManager $security,
        private RiskAssessor $risk,
        private KnownBrowsers $browsers,
        private SecurityNotifier $notifier,
        private Dispatcher $events,
        private UrlGenerator $urls,
    ) {}

    /**
     * After the password proved right: the code challenge, or the completed sign-in.
     *
     * @throws ValidationException
     */
    public function afterPassword(Request $request, Authenticatable $user, bool $remember): Response
    {
        $this->ensureActive($request, $user, 'login');

        if ($this->twoFactor->enrolled($user)) {
            return $this->challenge($request, $user, $remember);
        }

        if ($this->twoFactor->required($user, $this->redirects->panelFor($request)) && $this->twoFactor->passkeysSatisfy($user)) {
            $this->events->dispatch(new LoginFailed(UserKey::of($user), $this->sentinel->guardName(), 'second_factor_required'));

            throw ValidationException::withMessages([$this->config->identifier => [__('gcego-sentinel::messages.passkeys.required')]]);
        }

        return $this->complete($request, $user, $remember, 'password', false);
    }

    /**
     * After a verified passkey: a sign-in on its own when passkeys count as
     * two-factor, otherwise like a password (an authenticator code may follow).
     *
     * @throws ValidationException
     */
    public function afterPasskey(Request $request, Authenticatable $user, bool $remember): Response
    {
        $this->ensureActive($request, $user, 'passkey');

        if ($this->config->passkeysSatisfyTwoFactor) {
            return $this->complete($request, $user, $remember, 'passkey', true);
        }

        if ($this->twoFactor->enrolled($user)) {
            return $this->challenge($request, $user, $remember);
        }

        return $this->complete($request, $user, $remember, 'passkey', false);
    }

    /**
     * Sign the user in for good (all checks above). $multiFactor: a second factor was proved.
     *
     * @throws ValidationException
     */
    public function complete(Request $request, Authenticatable $user, bool $remember, string $method, bool $multiFactor): Response
    {
        $this->ensureActive($request, $user, $method === 'password' ? 'login' : 'two-factor');
        $destination = $this->redirects->afterLogin($request, $user);

        if ($destination->isRejected() || $destination->url === null) {
            return $this->reject($request, $user, $destination);
        }

        $remember = $remember && $this->config->remember && ! $this->twoFactor->required($user, $destination->panel);
        $guard = $this->sentinel->guard();
        $assessment = $this->assess($request, $user, $method);

        PendingLogin::forget($request->session());
        $guard->login($user, $remember);
        $request->session()->regenerate();
        $this->sessions->start($request->session(), $user, $multiFactor);

        if ($destination->panel?->isTenant() === true && $destination->tenant !== null) {
            try {
                $this->security->switchTenant($destination->tenant, $request->session());
            } catch (Throwable) {
                // The authorizer verified the membership a moment ago; ResolveTenant re-verifies it on the next request.
            }
        }

        if ($destination->twoFactorSetup && $destination->continue !== null) {
            $request->session()->put(SessionKeys::Continue, $destination->continue);
            $request->session()->flash('status', 'two-factor-required');
        }

        $this->events->dispatch(new LoginSucceeded(UserKey::of($user), $this->sentinel->guardName(), $method, $remember));
        $this->afterSignIn($request, $user, $assessment);

        if ($request->expectsJson()) {
            return new JsonResponse(['two_factor' => false, 'redirect' => $destination->url]);
        }

        return new RedirectResponse($destination->url);
    }

    /**
     * Refuse a sign-in to an inactive account with the generic message.
     *
     * @throws ValidationException
     */
    public function ensureActive(Request $request, Authenticatable $user, string $stage): void
    {
        $state = $this->accounts->state($user);

        if ($state->allowsAccess()) {
            return;
        }

        PendingLogin::forget($request->session());
        $this->events->dispatch(new AccountAccessDenied(UserKey::of($user), $state, $this->sentinel->guardName(), $stage));
        $this->events->dispatch(new LoginFailed(UserKey::of($user), $this->sentinel->guardName(), 'account_unavailable'));

        throw ValidationException::withMessages([$this->config->identifier => [__('gcego-sentinel::messages.account.unavailable_login')]])->status(403);
    }

    private function challenge(Request $request, Authenticatable $user, bool $remember): Response
    {
        // A new id for the half-signed-in state; the visitor is still a guest.
        $request->session()->migrate(true);
        PendingLogin::start($request->session(), UserKey::of($user), $remember, $this->config->challengeTimeout);

        if ($request->expectsJson()) {
            return new JsonResponse(['two_factor' => true, 'redirect' => $this->urls->route(SentinelRoutes::TwoFactorChallenge, [], false)]);
        }

        return new RedirectResponse($this->urls->route(SentinelRoutes::TwoFactorChallenge, [], false));
    }

    private function reject(Request $request, Authenticatable $user, Destination $destination): Response
    {
        $panel = $destination->panel;
        PendingLogin::forget($request->session());
        $this->events->dispatch(new LoginRejected(UserKey::of($user), $panel->id ?? '', $destination->tenant, (string) $destination->rejection));
        $request->session()->regenerateToken();
        $message = __('gcego-sentinel::messages.panel.denied', ['panel' => $panel?->title === null ? __('gcego-sentinel::messages.panel.this') : __($panel->title)]);

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], 403);
        }

        $login = $panel !== null ? $this->redirects->panelLogin($panel, $destination->tenant) : $this->redirects->login($request);

        return Redirect::to($login, $request)->withErrors([$this->config->identifier => $message]);
    }

    private function assess(Request $request, Authenticatable $user, string $method): RiskAssessment
    {
        try {
            return $this->risk->assess($request, $user, $method);
        } catch (Throwable) {
            return new RiskAssessment;
        }
    }

    private function afterSignIn(Request $request, Authenticatable $user, RiskAssessment $assessment): void
    {
        if ($assessment->level() !== RiskAssessment::Low) {
            $this->events->dispatch(new SuspiciousSignIn(UserKey::of($user), $assessment->level(), implode(',', $assessment->signals)));
        }

        if ($assessment->has('new_browser')) {
            $agent = UserAgent::parse($request->userAgent());
            $this->notifier->send($user, 'new_sign_in', ['browser' => ($agent->browser ?? 'Unknown browser').' / '.($agent->platform ?? 'unknown device')]);
        }

        $this->browsers->remember($request, $user);
    }
}
