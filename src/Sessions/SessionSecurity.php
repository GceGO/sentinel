<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Sessions;

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\Support\AppKey;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

/**
 * The security state Sentinel keeps in a signed-in session: when it signed
 * in, its last activity, its assurance (single or multi factor) and a keyed
 * fingerprint of the password hash it signed in with (so a password change
 * ends every other session, whatever the session driver). The password hash
 * itself is never put in the session.
 *
 * A per-account session epoch (in the shared cache) ends every session
 * signed in before it: "sign out everywhere", password changes and resets,
 * and security holds bump it.
 */
final readonly class SessionSecurity
{
    public function __construct(private Repository $settings, private TwoFactorPolicy $twoFactor, private Cache $cache) {}

    public function start(Session $session, Authenticatable $user, bool $multiFactor): void
    {
        $now = Date::now()->getTimestamp();
        $session->put(SessionKeys::AuthenticatedAt, $now);
        $session->put(SessionKeys::LastActivity, $now);
        $this->rebind($session, $user);
        $this->acknowledge($session, $user);
        $this->twoFactor->markSession($session, $multiFactor);
    }

    /**
     * End every session of the account signed in before now (the current one too, unless acknowledged again).
     */
    public function revokeAll(Authenticatable $user): void
    {
        try {
            $this->cache->forever(self::epochKey($user), max(Date::now()->getTimestamp(), $this->epoch($user) + 1));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Keep this session valid across the latest epoch (after revoking the others).
     */
    public function acknowledge(Session $session, Authenticatable $user): void
    {
        $session->put(SessionKeys::Epoch, $this->epoch($user));
    }

    public function revoked(Session $session, Authenticatable $user): bool
    {
        $seen = $session->get(SessionKeys::Epoch);

        if (! is_int($seen)) {
            // A session from before Sentinel tracked epochs: start tracking it now.
            $this->acknowledge($session, $user);

            return false;
        }

        return $seen < $this->epoch($user);
    }

    public function epoch(Authenticatable $user): int
    {
        try {
            $epoch = $this->cache->get(self::epochKey($user));
        } catch (\Throwable) {
            return 0;
        }

        return is_int($epoch) ? $epoch : 0;
    }

    /**
     * Keep the current session valid after its own password change.
     */
    public function rebind(Session $session, Authenticatable $user): void
    {
        $session->put(SessionKeys::PasswordFingerprint, $this->fingerprint($user));
    }

    public function fingerprint(Authenticatable $user): string
    {
        return hash_hmac('sha256', 'gcego-sentinel:password|'.$user->getAuthPassword(), AppKey::get($this->settings));
    }

    public function matches(Session $session, Authenticatable $user): bool
    {
        $stored = $session->get(SessionKeys::PasswordFingerprint);

        return ! is_string($stored) || hash_equals($stored, $this->fingerprint($user));
    }

    public function forget(Session $session): void
    {
        $session->forget([SessionKeys::AuthenticatedAt, SessionKeys::LastActivity, SessionKeys::PasswordFingerprint, SessionKeys::Assurance, SessionKeys::PendingLogin, SessionKeys::Epoch]);
    }

    private static function epochKey(Authenticatable $user): string
    {
        return 'gcego-sentinel:session-epoch:'.hash('sha256', (string) UserKey::of($user));
    }
}
