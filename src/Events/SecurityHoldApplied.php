<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * An account was placed on security hold (it cannot sign in until released).
 */
final readonly class SecurityHoldApplied implements SecurityEvent
{
    public function __construct(public string|int $userKey, public int $hold, public ?string $actor) {}

    public function auditType(): string
    {
        return 'security_hold.applied';
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
        return ['hold' => $this->hold, 'actor' => $this->actor];
    }
}
