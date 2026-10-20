<?php

declare(strict_types=1);

use Gcego\Sentinel\Presets\SentinelPresets;
use Illuminate\Support\Facades\Process;

/**
 * The real Tailwind CSS v4 build (scripts/build-assets.mjs, the same
 * compile -> scan -> build -> optimize steps as @tailwindcss/vite).
 */
beforeEach(function (): void {
    if (trim((string) shell_exec('command -v node')) === '' || ! is_dir(dirname(__DIR__, 4).'/node_modules/@tailwindcss/node')) {
        $this->markTestSkipped('Node.js or the monorepo\'s Tailwind CSS v4 packages are not available.');
    }
});

it('ships prebuilt assets that match the sources', function (): void {
    $result = Process::path(dirname(__DIR__, 2))->run(['node', 'scripts/build-assets.mjs', '--check']);

    expect($result->successful())->toBeTrue('resources/dist is stale: run composer build-assets. '.$result->output());
});

it('compiles every preset class, dark mode and reduced motion within a small budget', function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/dist/sentinel.css');
    $selector = static fn (string $class): string => '.'.preg_replace('/([^a-zA-Z0-9_-])/', '\\\\$1', $class);
    $classes = [];

    foreach (SentinelPresets::Presets as $list) {
        $classes = [...$classes, ...explode(' ', $list)];
    }

    $missing = array_values(array_filter(array_unique($classes), static fn (string $class): bool => ! str_contains($css, $selector($class))));
    recordPerformance(['stylesheet_minified_bytes' => strlen($css), 'stylesheet_gzip_bytes' => strlen((string) gzencode($css, 9)), 'script_bytes' => (int) filesize(dirname(__DIR__, 2).'/resources/dist/sentinel.js')]);

    expect($missing)->toBe([])
        ->and($css)->toContain(':root.dark')->toContain('prefers-reduced-motion')->toContain('--gcego-primary')
        // Raw budget covers the account centre, MFA stepper and dark variants; transfer size stays under 10 KB.
        ->and(strlen($css))->toBeLessThan(52 * 1024)
        ->and(strlen((string) gzencode($css, 9)))->toBeLessThan(10 * 1024);
});
