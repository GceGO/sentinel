<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Diagnostics;

use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\DiagnosticStatus;

/**
 * The authentication health check for dashboards and CLIs (gcego/studio,
 * gcego/cli, or `php artisan gcego:sentinel-inspect`): gcego/core's
 * DiagnosticReport format, read-only and free of secrets.
 */
final readonly class AuthenticationHealthCheck
{
    public function __construct(private SentinelInspector $inspector) {}

    public function run(): DiagnosticReport
    {
        return $this->inspector->inspect();
    }

    public function healthy(): bool
    {
        return ! $this->run()->hasFailures();
    }

    /**
     * @return array{status: string, failures: list<string>, warnings: list<string>}
     */
    public function summary(): array
    {
        $report = $this->run();

        return [
            'status' => $report->hasFailures() ? DiagnosticStatus::Failed->value : ($report->hasWarnings() ? DiagnosticStatus::Warning->value : DiagnosticStatus::Passed->value),
            'failures' => self::checks($report->failures()),
            'warnings' => self::checks($report->warnings()),
        ];
    }

    /**
     * @param  list<DiagnosticResult>  $results
     * @return list<string>
     */
    private static function checks(array $results): array
    {
        return array_map(static fn (DiagnosticResult $result): string => $result->check, $results);
    }
}
