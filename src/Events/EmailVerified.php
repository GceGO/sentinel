<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A user verified their email address through a signed link.
 */
final readonly class EmailVerified implements SecurityEvent
{
    public function __construct(public string|int $userKey) {}

    public function auditType(): string
    {
        return 'email.verified';
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
