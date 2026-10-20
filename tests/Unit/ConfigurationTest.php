<?php

declare(strict_types=1);

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\SafeUrl;

it('reads the shipped configuration with safe defaults', function (): void {
    $config = SentinelConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-sentinel.php');

    expect($config->enabled)->toBeTrue()
        ->and($config->registration)->toBeFalse()
        ->and($config->layout)->toBe('split')
        ->and($config->theme)->toBe('system')
        ->and($config->panels)->toBe([])
        ->and($config->pageEnabled(AuthenticationPage::Login))->toBeTrue()
        ->and($config->pageEnabled(AuthenticationPage::SessionExpired))->toBeTrue()
        ->and($config->assetsMode)->toBe('package')
        ->and(array_map(static fn ($field) => $field->name, $config->profileFields))->toBe(['name', 'email']);
});

it('rejects invalid configuration with the offending key', function (array $config, string $key): void {
    expect(fn () => SentinelConfig::fromArray($config))->toThrow(AuthenticationConfigurationException::class, $key);
})->with([
    'unknown page' => [['pages' => ['dashboard' => true]], 'gcego-sentinel.pages'],
    'layout' => [['appearance' => ['layout' => 'fancy']], 'gcego-sentinel.appearance.layout'],
    'theme' => [['appearance' => ['theme' => 'sepia']], 'gcego-sentinel.appearance.theme'],
    'home' => [['home' => 'https://evil.example'], 'gcego-sentinel.home'],
    'middleware' => [['routes' => ['middleware' => ['web; rm']]], 'gcego-sentinel.routes.middleware'],
    'route path' => [['routes' => ['account_security' => '../x']], 'gcego-sentinel.routes.account_security'],
    'logo' => [['branding' => ['logo' => 'javascript:alert(1)']], 'gcego-sentinel.branding.logo'],
    'http logo' => [['branding' => ['logo' => 'http://cdn.example/logo.svg']], 'gcego-sentinel.branding.logo'],
    'accent' => [['branding' => ['accent' => 'red; background:url(x)']], 'gcego-sentinel.branding.accent'],
    'legal' => [['branding' => ['legal' => ['Terms' => 'javascript:x']]], 'gcego-sentinel.branding.legal'],
    'registration field' => [['registration' => ['fields' => ['password' => ['label' => 'x']]]], 'gcego-sentinel.registration.fields'],
    'registration policy' => [['registration' => ['policy' => stdClass::class]], 'gcego-sentinel.registration.policy'],
    'panel id' => [['panels' => ['Admin Panel' => ['path' => 'admin']]], 'gcego-sentinel.panels'],
    'panel path' => [['panels' => ['admin' => ['path' => '{tenant}/x']]], 'gcego-sentinel.panels.admin.path'],
    'tenant home' => [['panels' => ['school' => ['path' => 'school/{tenant}', 'home' => '/school']]], 'gcego-sentinel.panels.school.home'],
    'panel permission' => [['panels' => ['admin' => ['path' => 'admin', 'permission' => 'not a permission!']]], 'gcego-sentinel.panels.admin.permission'],
    'panel layout' => [['panels' => ['admin' => ['path' => 'admin', 'layout' => 'fancy']]], 'gcego-sentinel.panels.admin.layout'],
    'duplicate panel path' => [['panels' => ['a' => ['path' => 'admin'], 'b' => ['path' => 'admin']]], 'gcego-sentinel.panels.b.path'],
    'vite without entries' => [['assets' => ['mode' => 'vite']], 'gcego-sentinel.assets.vite'],
    'type' => [['enabled' => 'yes'], 'gcego-sentinel.enabled'],
]);

it('accepts custom layouts and views', function (): void {
    $config = SentinelConfig::fromArray(['layouts' => ['school' => 'auth.school'], 'appearance' => ['layout' => 'school'], 'views' => ['login' => 'auth.login']]);

    expect($config->layout)->toBe('school')->and($config->views)->toBe(['login' => 'auth.login']);
});

it('matches panel paths and builds tenant homes', function (): void {
    $admin = new AuthenticationPanel('admin', 'admin');
    $school = new AuthenticationPanel('school', 'school/{tenant}', home: '/school/{tenant}/dashboard');

    expect($admin->match('/admin'))->toBe(['tenant' => null])
        ->and($admin->match('/admin/users'))->toBe(['tenant' => null])
        ->and($admin->match('/administration'))->toBeNull()
        ->and($school->match('/school/42/classes'))->toBe(['tenant' => '42'])
        ->and($school->match('/school/a%3Ab'))->toBe(['tenant' => 'a:b'])
        ->and($school->match('/school/%3Cscript%3E'))->toBeNull()
        ->and($school->home('42'))->toBe('/school/42/dashboard')
        ->and($school->home('a/b'))->toBe('/school/a%2Fb/dashboard')
        ->and($school->isTenant())->toBeTrue()
        ->and($admin->loginUri())->toBe('admin/login');
});

it('merges branding layers and derives an accessible accent foreground', function (): void {
    $base = new AuthenticationBranding(name: 'GCEGO', tagline: 'Learn', legal: ['Privacy' => '/privacy']);
    $merged = $base->merge(new AuthenticationBranding(name: 'GBHS Limbe', accent: '#fde047'));

    expect($merged->name)->toBe('GBHS Limbe')
        ->and($merged->tagline)->toBe('Learn')
        ->and($merged->legal)->toBe(['Privacy' => '/privacy'])
        ->and($merged->accentForeground())->toBe('#0f172a')
        ->and((new AuthenticationBranding(accent: '#1e3a8a'))->accentForeground())->toBe('#ffffff')
        // gcego/swatches darkens a yellow too light for white surfaces and derives dark-mode values.
        ->and($merged->css())->toStartWith(':root:not(.dark){')
        ->and($merged->css())->toContain(':root.dark{')
        ->and($merged->css())->not->toContain('--gcego-primary:#fde047')
        ->and((new AuthenticationBranding(accent: '#1d4ed8'))->css())->toContain('--gcego-primary:#1d4ed8;')
        ->and($merged->initials())->toBe('GL')
        ->and(AuthenticationBranding::fromArray(['accent' => '#ABC'])->accent)->toBe('#aabbcc');
});

it('accepts only safe asset and link URLs', function (): void {
    expect(SafeUrl::asset('/images/logo.svg'))->toBe('/images/logo.svg')
        ->and(SafeUrl::asset('https://cdn.example/logo.svg'))->toBe('https://cdn.example/logo.svg')
        ->and(SafeUrl::asset('//cdn.example/logo.svg'))->toBeNull()
        ->and(SafeUrl::asset('data:image/svg+xml;base64,AAA'))->toBeNull()
        ->and(SafeUrl::asset('/x" onerror="alert(1)'))->toBeNull()
        ->and(SafeUrl::link('mailto:help@example.test'))->toBe('mailto:help@example.test')
        ->and(SafeUrl::link('http://example.test/terms'))->toBe('http://example.test/terms')
        ->and(SafeUrl::link('javascript:alert(1)'))->toBeNull()
        ->and(SafeUrl::color('#FFF'))->toBe('#ffffff')
        ->and(SafeUrl::color('blue'))->toBeNull();
});
