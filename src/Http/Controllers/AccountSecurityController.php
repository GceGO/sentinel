<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Accounts\AccountAccess;
use Gcego\Sentinel\Audit\SecurityEventRecord;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Redirects\SafeRedirectValidator;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The account security center (/account/security): the signed-in user's
 * own profile summary, verification and account state, recent security
 * activity, and the Livewire panels for profile, password, two-factor,
 * passkeys and browser sessions. It only ever shows the current user's data.
 */
final readonly class AccountSecurityController
{
    public function __construct(
        private SentinelManager $sentinel,
        private TwoFactorPolicy $twoFactor,
        private AccountAccess $accounts,
        private SafeRedirectValidator $redirects,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->sentinel->guard()->user();
        abort_if($user === null, 401);
        $context = $this->sentinel->context($request, AuthenticationPage::AccountSecurity);
        $enrolled = $this->twoFactor->satisfiable($user);
        $required = $this->twoFactor->required($user, $context->panel);
        $continue = $this->redirects->internal($request->session()->get(SessionKeys::Continue), $request);

        if ($continue !== null && $enrolled) {
            $request->session()->forget(SessionKeys::Continue);
        }

        return $this->sentinel->page($request, AuthenticationPage::AccountSecurity, [
            'user' => $user,
            'name' => self::text(data_get($user, 'name')),
            'email' => self::text(data_get($user, 'email')),
            'emailVerified' => $user instanceof MustVerifyEmail ? $user->hasVerifiedEmail() : null,
            'twoFactorEnrolled' => $enrolled,
            'twoFactorRequired' => $required,
            'accountState' => $this->accounts->state($user),
            'continue' => $continue !== null && (! $required || $enrolled) ? $continue : null,
            'activity' => $this->activity($user),
        ]);
    }

    /**
     * The user's own latest security events (empty when the audit table is not there).
     *
     * @return Collection<int, SecurityEventRecord>
     */
    private function activity(Authenticatable $user): Collection
    {
        if (! $this->sentinel->config()->audit || $this->sentinel->config()->auditSink !== 'database') {
            return new Collection;
        }

        try {
            return SecurityEventRecord::recentFor(UserKey::of($user), 8);
        } catch (Throwable) {
            return new Collection;
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
