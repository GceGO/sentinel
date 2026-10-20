<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A sign-in attempt failed. $userKey is set only when the identifier
 * matched an account (never shown to the visitor). $reason is "credentials",
 * "throttled", "account_unavailable" or "passkey".
 */
final readonly class LoginFailed implements SecurityEvent
{
    public function __construct(public string|int|null $userKey, public string $guard, public string $reason) {}

    public function auditType(): string
    {
        return 'login.failed';
    }

    public function auditUserKey(): string|int|null
    {
        return $this->userKey;
    }

    public function auditOutcome(): string
    {
        return 'failed';
    }

    public function auditContext(): array
    {
        return ['guard' => $this->guard, 'reason' => $this->reason];
    }
}
