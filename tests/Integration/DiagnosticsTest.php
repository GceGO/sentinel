<?php

declare(strict_types=1);

use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Sentinel\Diagnostics\AuthenticationHealthCheck;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('reports a healthy setup without secrets', function (): void {
    $report = app(SentinelManager::class)->inspect();

    expect($report->hasFailures())->toBeFalse()
        ->and($report->result('sentinel.two_factor')?->status)->toBe(DiagnosticStatus::Passed)
        ->and($report->result('sentinel.database')?->status)->toBe(DiagnosticStatus::Passed)
        ->and($report->result('sentinel.rate_limiters')?->status)->toBe(DiagnosticStatus::Passed)
        ->and($report->result('sentinel.audit')?->details['sink'])->toBe('database')
        ->and($report->result('sentinel.cache')?->details['store'])->toBe('array')
        ->and($report->result('sentinel.localization')?->details['locales'])->toBe(['en', 'fr']);

    $json = json_encode($report->toArray());
    expect($json)->not->toContain((string) config('app.key'))->not->toContain('base64:');
});

it('warns about panels that admit everyone or allow registration', function (): void {
    expect(app(SentinelManager::class)->inspect()->result('sentinel.panels')?->status)->toBe(DiagnosticStatus::Warning)
        ->and(app(SentinelManager::class)->inspect()->result('sentinel.panels')?->details['registration'])->toBe(['student']);
});

it('fails on a token guard, insecure cookies, disabled rate limits and two-factor requirements without the feature', function (): void {
    $this->reboot([
        'gcego-sentinel.guard' => 'api',
        'auth.guards.api' => ['driver' => 'token', 'provider' => 'users'],
        'session.http_only' => false,
        'gcego-sentinel.two_factor.required_permissions' => ['security.manage'],
        'gcego-sentinel.features.two_factor' => false,
        'gcego-sentinel.rate_limits.enabled' => false,
    ]);

    $report = app(AuthenticationHealthCheck::class)->run();

    expect($report->result('sentinel.guard')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('sentinel.session')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('sentinel.two_factor')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('sentinel.rate_limiters')?->status)->toBe(DiagnosticStatus::Failed)
        ->and(app(AuthenticationHealthCheck::class)->healthy())->toBeFalse()
        ->and(app(AuthenticationHealthCheck::class)->summary()['failures'])->toContain('sentinel.guard');
});

it('fails when Sentinel\'s tables or the two-factor columns are missing, and warns about long reset links', function (): void {
    $this->reboot(['auth.passwords.users.expire' => 1440]);
    Schema::drop('sentinel_security_holds');
    Schema::table('users', fn ($table) => $table->dropColumn('two_factor_confirmed_at'));

    $report = app(AuthenticationHealthCheck::class)->run();

    expect($report->result('sentinel.database')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('sentinel.database')?->details['missing'])->toBe(['sentinel_security_holds'])
        ->and($report->result('sentinel.two_factor')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('sentinel.broker')?->status)->toBe(DiagnosticStatus::Warning);
});

it('fails on an array cache or plain-http passkey origins in production', function (): void {
    $this->reboot(['session.secure' => true]);
    $this->app->detectEnvironment(static fn (): string => 'production');

    $report = app(AuthenticationHealthCheck::class)->run();

    expect($report->result('sentinel.cache')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('sentinel.passkeys')?->status)->toBe(DiagnosticStatus::Failed);
});

it('prints the health check and fails CI on failures', function (): void {
    $this->artisan('gcego:sentinel-inspect')->expectsOutputToContain('sentinel.guard')->assertSuccessful();
    $this->artisan('gcego:sentinel-inspect --json')->expectsOutputToContain('"check": "sentinel.guard"')->assertSuccessful();

    $this->reboot(['session.http_only' => false]);
    $this->artisan('gcego:sentinel-inspect')->assertFailed();
});

it('installs idempotently, never overwriting, with a dry run that changes nothing', function (): void {
    $config = config_path('gcego-sentinel.php');
    $views = resource_path('views/vendor/gcego-sentinel');
    File::delete($config);
    File::deleteDirectory($views);

    try {
        $this->artisan('gcego:sentinel-install --dry-run --views')->expectsOutputToContain('would publish')->assertSuccessful();
        expect(File::exists($config))->toBeFalse()->and(File::isDirectory($views))->toBeFalse();

        $this->artisan('gcego:sentinel-install --views')->expectsOutputToContain('published')->assertSuccessful();
        expect(File::exists($config))->toBeTrue()->and(File::exists($views.'/pages/login.blade.php'))->toBeTrue();

        File::put($config, "<?php return ['customized' => true];");
        File::put($views.'/pages/login.blade.php', 'customized');

        $this->artisan('gcego:sentinel-install --views')->expectsOutputToContain('already published')->assertSuccessful();
        expect(File::get($config))->toContain('customized')->and(File::get($views.'/pages/login.blade.php'))->toBe('customized');
    } finally {
        File::delete($config);
        File::deleteDirectory($views);
    }
});
