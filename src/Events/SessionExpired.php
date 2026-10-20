<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A signed-in session was ended by policy ($reason: "idle", "absolute" or "password_changed").
 */
final readonly class SessionExpired implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $guard, public string $reason) {}

    public function auditType(): string
    {
        return 'session.expired';
    }

    public function auditUserKey(): string|int
    {
        return $this->userKey;
    }

    public function auditOutcome(): string
    {
        return 'recorded';
    }

    public function auditContext(): array
    {
        return ['guard' => $this->guard, 'reason' => $this->reason];
    }
}
