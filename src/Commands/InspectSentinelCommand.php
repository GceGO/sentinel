<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Commands;

use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Sentinel\Diagnostics\AuthenticationHealthCheck;
use Illuminate\Console\Command;

/**
 * Prints the authentication health check (read-only). Exits with 1 when a check failed, for CI.
 */
final class InspectSentinelCommand extends Command
{
    protected $signature = 'gcego:sentinel-inspect {--json : Print the report as JSON}';

    protected $description = 'Check the authentication setup: guard, routes, tables, sessions, cache, two-factor, passkeys, panels and assets';

    public function handle(AuthenticationHealthCheck $health): int
    {
        $report = $health->run();

        if ($this->option('json') === true) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Check', 'Status', 'Message'], array_map(static fn (DiagnosticResult $result): array => [$result->check, $result->status->value, $result->message], $report->results()));

        if ($report->hasFailures()) {
            $this->components->error(count($report->failures()).' check(s) failed.');

            return self::FAILURE;
        }

        $this->components->info($report->hasWarnings() ? 'Authentication works; review the warnings.' : 'Authentication is healthy.');

        return self::SUCCESS;
    }
}
