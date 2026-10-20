<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Security;

use Gcego\Security\Permissions\Permission;
use Gcego\Security\SecurityManager;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\MultiFactor\TwoFactorAuthentication;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Passkeys\PasskeyAuthentication;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;

/**
 * Who must use two-factor authentication: members of a panel with
 * "two_factor" => true, and holders of any permission in
 * gcego-sentinel.two_factor.required_permissions (checked through
 * gcego/security). Enforced server-side by the login pipeline (every
 * sign-in method) and the sentinel.two-factor middleware; the UI only
 * explains it. A passkey sign-in counts as two-factor when
 * gcego-sentinel.passkeys.satisfies_two_factor is true.
 */
final readonly class TwoFactorPolicy
{
    public function __construct(
        private SentinelConfig $config,
        private SecurityManager $security,
        private TwoFactorAuthentication $twoFactor,
        private Container $container,
    ) {}

    public function available(): bool
    {
        return $this->config->enabled && $this->config->twoFactor;
    }

    public function required(Authenticatable $user, ?AuthenticationPanel $panel = null): bool
    {
        if (! $this->available()) {
            return false;
        }

        if ($panel?->twoFactor === true) {
            return true;
        }

        if ($this->config->twoFactorPermissions === []) {
            return false;
        }

        return $this->security->actingAs($user, null, function () {
            foreach ($this->config->twoFactorPermissions as $permission) {
                if ($this->security->check(new Permission($permission))->allowed) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Whether the user has confirmed two-factor authentication.
     */
    public function enrolled(Authenticatable $user): bool
    {
        return $this->available() && $this->twoFactor->enrolled($user);
    }

    /**
     * Whether a passkey sign-in can stand in for two-factor authentication for this user.
     */
    public function passkeysSatisfy(Authenticatable $user): bool
    {
        if (! $this->config->passkeys || ! $this->config->passkeysSatisfyTwoFactor) {
            return false;
        }

        $passkeys = $this->container->make(PasskeyAuthentication::class);

        return $passkeys->supported() && $passkeys->count($user) > 0;
    }

    /**
     * The user has a second factor: a confirmed authenticator, or passkeys that count as one.
     */
    public function satisfiable(Authenticatable $user): bool
    {
        return $this->enrolled($user) || $this->passkeysSatisfy($user);
    }

    /**
     * Required but no second factor yet: the user must enroll before continuing.
     */
    public function missing(Authenticatable $user, ?AuthenticationPanel $panel = null): bool
    {
        return $this->required($user, $panel) && ! $this->satisfiable($user);
    }

    /**
     * Whether this session was established with more than a password
     * (authenticator code, recovery code, a passkey that counts, or an
     * enrollment confirmed in this session).
     */
    public function sessionIsMultiFactor(Session $session): bool
    {
        return $session->get(SessionKeys::Assurance) === SessionKeys::MultiFactor;
    }

    public function markSession(Session $session, bool $multiFactor): void
    {
        $session->put(SessionKeys::Assurance, $multiFactor ? SessionKeys::MultiFactor : SessionKeys::SingleFactor);
    }
}
