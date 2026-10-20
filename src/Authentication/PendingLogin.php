<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Authentication;

use Gcego\Sentinel\Context\SessionKeys;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

/**
 * A sign-in whose password was right and that waits for its second factor.
 * It lives only in the (regenerated) session, expires after
 * two_factor.challenge_timeout seconds and allows two_factor.challenge_attempts
 * wrong codes; it never makes the visitor authenticated.
 */
final readonly class PendingLogin
{
    public function __construct(
        public string|int $userKey,
        public bool $remember,
        public int $expiresAt,
        public int $attempts = 0,
    ) {}

    public static function start(Session $session, string|int $userKey, bool $remember, int $timeout): self
    {
        $pending = new self($userKey, $remember, Date::now()->getTimestamp() + $timeout);
        $pending->store($session);

        return $pending;
    }

    /**
     * The session's pending sign-in, or null when there is none or it expired.
     */
    public static function from(Session $session): ?self
    {
        $value = $session->get(SessionKeys::PendingLogin);

        if (! is_array($value) || ! (is_int($value['user'] ?? null) || is_string($value['user'] ?? null)) || ! is_int($value['expires_at'] ?? null) || ! is_int($value['attempts'] ?? null)) {
            return null;
        }

        if ($value['expires_at'] < Date::now()->getTimestamp()) {
            $session->forget(SessionKeys::PendingLogin);

            return null;
        }

        return new self($value['user'], ($value['remember'] ?? false) === true, $value['expires_at'], $value['attempts']);
    }

    public function failed(Session $session): self
    {
        $pending = new self($this->userKey, $this->remember, $this->expiresAt, $this->attempts + 1);
        $pending->store($session);

        return $pending;
    }

    public static function forget(Session $session): void
    {
        $session->forget(SessionKeys::PendingLogin);
    }

    private function store(Session $session): void
    {
        $session->put(SessionKeys::PendingLogin, ['user' => $this->userKey, 'remember' => $this->remember, 'expires_at' => $this->expiresAt, 'attempts' => $this->attempts]);
    }
}
