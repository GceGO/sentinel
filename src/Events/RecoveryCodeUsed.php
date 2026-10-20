<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A recovery code was redeemed (it can never be used again).
 */
final readonly class RecoveryCodeUsed implements SecurityEvent
{
    public function __construct(public string|int $userKey, public int $remaining) {}

    public function auditType(): string
    {
        return 'two_factor.recovery_code_used';
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
        return ['remaining' => $this->remaining];
    }
}
