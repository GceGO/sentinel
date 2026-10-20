<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A password was reset through a reset link; every session of the account was ended.
 */
final readonly class PasswordResetCompleted implements SecurityEvent
{
    public function __construct(public string|int $userKey, public int $sessionsEnded) {}

    public function auditType(): string
    {
        return 'password.reset';
    }

    public function auditUserKey(): string|int
    {
        return $this->userKey;
    }

    public function auditOutcome(): string
    {
        return 'succeeded';
    }

    public function auditContext(): array
    {
        return ['sessions_ended' => $this->sessionsEnded];
    }
}
