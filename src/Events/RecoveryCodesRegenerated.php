<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * New recovery codes replaced every previous one.
 */
final readonly class RecoveryCodesRegenerated implements SecurityEvent
{
    public function __construct(public string|int $userKey, public int $count) {}

    public function auditType(): string
    {
        return 'two_factor.recovery_codes_regenerated';
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
        return ['count' => $this->count];
    }
}
