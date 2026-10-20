<?php

declare(strict_types=1);

use Gcego\Sentinel\SentinelConfig;

it('accepts the README configuration example', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:config -->\s*```php\n(.*?)```/s', $readme, $match);
    $code = preg_replace('/^\/\/.*\n/m', '', $match[1] ?? '');

    $config = SentinelConfig::fromArray(eval($code));

    expect($config->panels)->toHaveKeys(['admin', 'teacher', 'institution'])
        ->and($config->panels['admin']->twoFactor)->toBeTrue()
        ->and($config->panels['institution']->isTenant())->toBeTrue()
        ->and($config->branding->accent)->toBe('#1d4ed8')
        ->and($config->accountStateAttribute)->toBe('status');
});
