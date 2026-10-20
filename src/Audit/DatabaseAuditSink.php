<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

use Gcego\Sentinel\Contracts\SecurityAuditSink;
use Throwable;

/**
 * Stores audit entries in sentinel_security_events (append-only).
 */
final readonly class DatabaseAuditSink implements SecurityAuditSink
{
    public function record(AuditEntry $entry): void
    {
        try {
            SecurityEventRecord::query()->create([
                'type' => $entry->type,
                'outcome' => $entry->outcome,
                'user_key' => $entry->userKey,
                'context' => $entry->context === [] ? null : $entry->context,
                'session_reference' => $entry->sessionReference,
                'tenant' => $entry->tenant,
                'ip_address' => $entry->ipAddress,
                'user_agent' => $entry->userAgent,
                'occurred_at' => $entry->occurredAt,
            ]);
        } catch (Throwable) {
            // A missing table or a database outage must not break sign-in.
        }
    }
}
