<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A wrong or replayed code at the two-factor challenge ($method: "code" or "recovery_code").
 */
final readonly class TwoFactorChallengeFailed implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $method, public bool $exhausted) {}

    public function auditType(): string
    {
        return 'two_factor.challenge_failed';
    }

    public function auditUserKey(): string|int
    {
        return $this->userKey;
    }

    public function auditOutcome(): string
    {
        return 'failed';
    }

    public function auditContext(): array
    {
        return ['method' => $this->method, 'exhausted' => $this->exhausted];
    }
}
