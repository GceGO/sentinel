<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * An account was created through Sentinel's registration (no roles granted).
 */
final readonly class RegistrationCompleted implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $guard, public ?string $panel = null) {}

    public function auditType(): string
    {
        return 'registration.completed';
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
        return ['guard' => $this->guard, 'panel' => $this->panel];
    }
}
