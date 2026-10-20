<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

use Gcego\Sentinel\Contracts\SecurityAuditSink;
use Illuminate\Log\LogManager;
use Throwable;

/**
 * Writes audit entries to a Laravel log channel as structured context
 * (ship it to your SIEM).
 */
final readonly class LogAuditSink implements SecurityAuditSink
{
    public function __construct(private LogManager $logs, private ?string $channel = null) {}

    public function record(AuditEntry $entry): void
    {
        try {
            $this->logs->channel($this->channel)->info('sentinel.'.$entry->type, $entry->toArray());
        } catch (Throwable) {
            // Auditing must never break the operation it observes.
        }
    }
}
