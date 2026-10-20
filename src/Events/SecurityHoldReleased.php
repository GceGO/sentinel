<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A security hold was released.
 */
final readonly class SecurityHoldReleased implements SecurityEvent
{
    public function __construct(public string|int $userKey, public int $hold, public ?string $actor) {}

    public function auditType(): string
    {
        return 'security_hold.released';
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
