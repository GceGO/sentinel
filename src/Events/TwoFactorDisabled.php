<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * Two-factor authentication was turned off ($administrative: by an operator, for account recovery).
 */
final readonly class TwoFactorDisabled implements SecurityEvent
{
    public function __construct(public string|int $userKey, public bool $administrative = false) {}

    public function auditType(): string
    {
        return 'two_factor.disabled';
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
        return ['administrative' => $this->administrative];
    }
}
