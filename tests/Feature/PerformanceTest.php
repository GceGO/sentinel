<?php

declare(strict_types=1);

use Gcego\Sentinel\Livewire\BrowserSessions;
use Gcego\Sentinel\Livewire\TwoFactorManagement;
use Gcego\Sentinel\Livewire\UpdatePasswordForm;
use Livewire\Livewire;

it('renders guest pages without queries, quickly', function (): void {
    $this->get('/login');
    $timings = [];

    foreach (['/login', '/admin/login', '/forgot-password', '/session-expired'] as $url) {
        $started = hrtime(true);
        $queries = queriesDuring(fn () => $this->get($url)->assertOk());
        $timings[$url] = (hrtime(true) - $started) / 1e6;

        expect($queries)->toBe([]);
    }

    $html = (string) $this->get('/login')->getContent();
    recordPerformance(['login_render_ms' => round($timings['/login'], 1), 'panel_login_render_ms' => round($timings['/admin/login'], 1), 'login_html_bytes' => strlen($html)]);

    expect(max($timings))->toBeLessThan(500)
        ->and(substr_count($html, '<script'))->toBe(1)
        ->and(substr_count($html, 'rel="stylesheet"'))->toBe(1);
});

it('keeps the account security page to a handful of queries and small snapshots', function (): void {
    $this->reboot(['session.driver' => 'database']);
    $user = user();
    $this->actingAs($user)->get('/account/security');

    $html = '';
    $queries = queriesDuring(function () use (&$html): void {
        $html = (string) $this->get('/account/security')->assertOk()->getContent();
    });
    $snapshots = array_map(static fn (string $name): int => strlen(snapshotOf($html, $name)), ['gcego-sentinel.profile-information', 'gcego-sentinel.update-password', 'gcego-sentinel.two-factor', 'gcego-sentinel.browser-sessions']);

    recordPerformance(['account_security_queries' => count($queries), 'account_security_html_bytes' => strlen($html), 'livewire_snapshot_max_bytes' => max($snapshots)]);

    // Session read and write, security hold, passkey count and list, recent activity, device list.
    expect(count($queries))->toBeLessThanOrEqual(8)
        ->and(max($snapshots))->toBeLessThan(1000);
});

it('does not query for anything but the user in Livewire security components', function (): void {
    $this->actingAs(user());

    foreach ([UpdatePasswordForm::class, TwoFactorManagement::class, BrowserSessions::class] as $component) {
        expect(count(queriesDuring(fn () => Livewire::test($component))))->toBeLessThanOrEqual(1);
    }
});
