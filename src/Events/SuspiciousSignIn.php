<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A completed sign-in raised risk signals (never proof of fraud). $signals is a comma-separated list.
 */
final readonly class SuspiciousSignIn implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $level, public string $signals) {}

    public function auditType(): string
    {
        return 'login.suspicious';
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
        return ['level' => $this->level, 'signals' => $this->signals];
    }
}
