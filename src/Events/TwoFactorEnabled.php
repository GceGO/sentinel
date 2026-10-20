<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * Two-factor authentication was confirmed with a code and is now on.
 */
final readonly class TwoFactorEnabled implements SecurityEvent
{
    public function __construct(public string|int $userKey) {}

    public function auditType(): string
    {
        return 'two_factor.enabled';
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
        return [];
    }
}
