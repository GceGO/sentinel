<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A Sentinel event the security audit trail records. Implementations carry
 * identifiers, codes and counts only: never passwords, codes, tokens,
 * secrets, session ids or personal data beyond the user's key.
 */
interface SecurityEvent
{
    /**
     * A stable dotted type such as "login.succeeded".
     */
    public function auditType(): string;

    public function auditUserKey(): string|int|null;

    /**
     * "succeeded", "failed", "denied" or "recorded".
     */
    public function auditOutcome(): string;

    /**
     * Extra non-secret details (scalars only).
     *
     * @return array<string, string|int|bool|null>
     */
    public function auditContext(): array;
}
