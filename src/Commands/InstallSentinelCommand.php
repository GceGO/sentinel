<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Commands;

use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Sentinel\Diagnostics\AuthenticationHealthCheck;
use Gcego\Sentinel\SentinelServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Safe, idempotent setup: publishes what is missing and reports the rest.
 *
 *  - copies config/gcego-sentinel.php only when it does not exist;
 *  - --views / --lang copy only files that do not exist yet;
 *  - --migrations copies Sentinel's migrations into database/migrations
 *    (otherwise they run from the package); it never runs them;
 *  - --dry-run prints what would happen and changes nothing;
 *  - ends with the authentication health check (--strict: exit 1 on failures).
 *
 * Non-interactive, so it runs in CI.
 */
final class InstallSentinelCommand extends Command
{
    protected $signature = 'gcego:sentinel-install
        {--dry-run : Show what would change without changing anything}
        {--views : Also publish Sentinel\'s Blade views (missing files only)}
        {--lang : Also publish Sentinel\'s translations (missing files only)}
        {--migrations : Also publish Sentinel\'s migrations (missing files only)}
        {--strict : Exit with 1 when a health check fails}';

    protected $description = 'Install GCEGO Sentinel safely (idempotent, never overwrites)';

    public function handle(Filesystem $files, AuthenticationHealthCheck $health): int
    {
        $dry = $this->option('dry-run') === true;
        $root = dirname(__DIR__, 2);

        if ($dry) {
            $this->components->warn('Dry run: nothing will be changed.');
        }

        $this->copy($files, $root.'/config/gcego-sentinel.php', $this->laravel->configPath('gcego-sentinel.php'), 'configuration', $dry);

        if ($this->option('views') === true) {
            $this->copyDirectory($files, $root.'/resources/views', $this->laravel->resourcePath('views/vendor/'.SentinelServiceProvider::Views), 'views', $dry);
        }

        if ($this->option('migrations') === true) {
            $this->copyDirectory($files, $root.'/database/migrations', $this->laravel->databasePath('migrations'), 'migrations', $dry);
        }

        if ($this->option('lang') === true) {
            $this->copyDirectory($files, $root.'/resources/lang', $this->laravel->langPath('vendor/'.SentinelServiceProvider::Views), 'translations', $dry);
        }

        $this->components->info('Assets: Sentinel serves its prebuilt stylesheet and script; no build step is needed (see docs/installation.md to use Vite instead).');
        $this->components->info('Database: run "php artisan migrate" to create Sentinel\'s tables (recovery codes, audit events, security holds, known browsers).');

        $report = $health->run();

        foreach ($report->results() as $result) {
            /** @var DiagnosticResult $result */
            $line = $result->check.': '.$result->message;

            match ($result->status) {
                DiagnosticStatus::Passed => $this->components->twoColumnDetail($result->check, '<fg=green>passed</>'),
                DiagnosticStatus::Warning => $this->components->warn($line),
                DiagnosticStatus::Failed => $this->components->error($line),
            };
        }

        return $this->option('strict') === true && $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function copy(Filesystem $files, string $from, string $to, string $label, bool $dry): void
    {
        if ($files->exists($to)) {
            $this->components->twoColumnDetail(ucfirst($label), '<fg=green>already published</> (left unchanged)');

            return;
        }

        if (! $dry) {
            $files->ensureDirectoryExists(dirname($to));
            $files->copy($from, $to);
        }

        $this->components->twoColumnDetail(ucfirst($label), $dry ? 'would publish '.$this->relative($to) : 'published '.$this->relative($to));
    }

    private function copyDirectory(Filesystem $files, string $from, string $to, string $label, bool $dry): void
    {
        $copied = 0;
        $kept = 0;

        foreach ($files->allFiles($from) as $file) {
            $target = $to.'/'.$file->getRelativePathname();

            if ($files->exists($target)) {
                $kept++;

                continue;
            }

            if (! $dry) {
                $files->ensureDirectoryExists(dirname($target));
                $files->copy($file->getPathname(), $target);
            }

            $copied++;
        }

        $this->components->twoColumnDetail(ucfirst($label), sprintf('%s %d file(s), kept %d existing', $dry ? 'would publish' : 'published', $copied, $kept));
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace($this->laravel->basePath(), '', $path), '/');
    }
}
