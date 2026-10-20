<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

use Gcego\Sentinel\Contracts\SecurityAuditSink;

/**
 * Discards audit entries (gcego-sentinel.audit.sink = "null").
 */
final readonly class NullAuditSink implements SecurityAuditSink
{
    public function record(AuditEntry $entry): void {}
}
