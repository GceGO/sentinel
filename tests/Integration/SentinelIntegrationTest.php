<?php

declare(strict_types=1);

use Gcego\Core\Packages\PackageManager;
use Gcego\Sentinel\Http\Middleware\EnforceSessionSecurity;
use Gcego\Sentinel\Http\Middleware\EnsureRegistrationIsAllowed;
use Gcego\Sentinel\Routing\SentinelRouteRegistrar;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Tests\Fixtures\ApplicationRoutesProvider;
use Gcego\Sentinel\Tests\TestCase;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

it('registers as a GCEGO package with its dependencies', function (): void {
    $descriptor = app(PackageManager::class)->descriptor('gcego/sentinel');

    expect($descriptor->requires)->toContain('gcego/security')->toContain('gcego/chroma')
        ->and($descriptor->capabilities)->toContain('authentication.engine');
});

it('handles every authentication endpoint with Sentinel\'s own controllers', function (): void {
    foreach (['login', 'login.store', 'logout', 'two-factor.login.store', 'password.email', 'password.update', 'password.confirm.store', 'verification.verify', 'register.store', 'passkey.login'] as $name) {
        expect(app('router')->getRoutes()->getByName($name)?->getActionName())->toStartWith('Gcego\\Sentinel\\Http\\Controllers\\');
    }
});

it('serves every authentication endpoint under the usual names without duplicates', function (): void {
    $names = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->getName())->filter()->countBy();

    expect($names->filter(fn (int $count): bool => $count > 1)->all())->toBe([])
        ->and($names->keys()->all())->toContain(
            'login', 'login.store', 'logout', 'register', 'register.store',
            'password.request', 'password.email', 'password.reset', 'password.update',
            'password.confirm', 'password.confirm.store', 'password.confirmation',
            'two-factor.login', 'two-factor.login.store',
            'verification.notice', 'verification.verify', 'verification.send',
            'passkey.login-options', 'passkey.login', 'passkey.registration-options', 'passkey.store',
            'sentinel.account.security', 'sentinel.account.recovery', 'sentinel.panels.admin.login', 'sentinel.panels.institution.login',
        );
});

it('leaves the application\'s own login page in place and reports the conflict', function (): void {
    TestCase::$applicationProviders = [ApplicationRoutesProvider::class];
    $this->reboot([]);
    $user = user();

    $this->get('/login')->assertOk()->assertSee('Application login');
    expect(array_column(app(SentinelRouteRegistrar::class)->conflicts(), 'name'))->toContain('login');

    // Sentinel's sign-in endpoint still works.
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
});

it('guards and throttles the endpoints that need it', function (): void {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('register.store')->gatherMiddleware())->toContain(EnsureRegistrationIsAllowed::class)->toContain('throttle:gcego-sentinel-register')
        ->and($routes->getByName('password.email')->gatherMiddleware())->toContain('throttle:gcego-sentinel-password-reset-request')
        ->and($routes->getByName('password.update')->gatherMiddleware())->toContain('throttle:gcego-sentinel-password-reset')
        ->and($routes->getByName('two-factor.login.store')->gatherMiddleware())->toContain('throttle:gcego-sentinel-two-factor')
        ->and($routes->getByName('password.confirm.store')->gatherMiddleware())->toContain('throttle:gcego-sentinel-confirm-password')
        ->and($routes->getByName('verification.verify')->gatherMiddleware())->toContain('signed')
        ->and($routes->getByName('passkey.login')->gatherMiddleware())->toContain('throttle:gcego-sentinel-passkeys')->toContain('guest:web')
        ->and($routes->getByName('passkey.store')->gatherMiddleware())->toContain('auth:web')
        ->and(app('router')->getMiddlewareGroups()['web'])->toContain(EnforceSessionSecurity::class);
});

it('switches laravel/passkeys\' own routes off (Sentinel serves the ceremonies)', function (): void {
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        expect($route->getActionName())->not->toStartWith('Laravel\\Passkeys\\');
    }
});

it('registers controller routes only, so routes can be cached', function (): void {
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        if (str_starts_with($route->getActionName(), 'Gcego\\Sentinel\\')) {
            expect($route->getAction('uses'))->toBeString();
        }
    }
});

it('keeps configuration cacheable (plain data only)', function (): void {
    $config = config('gcego-sentinel');

    expect(eval('return '.var_export($config, true).';'))->toBe($config);
});

it('skips routes whose name or URL the application already uses', function (): void {
    $router = new Router(new Dispatcher, new Container);
    $router->get('teacher/login', static fn (): string => 'Existing teacher login');
    $router->get('account/security', static fn (): string => 'Existing')->name('sentinel.account.security');
    $router->post('logout', static fn (): string => 'Existing logout');

    $registrar = app(SentinelRouteRegistrar::class);
    $registered = $registrar->register($router);

    expect($registered)->toContain('sentinel.panels.admin.login')->toContain('login')->not->toContain('sentinel.panels.teacher.login')->not->toContain('sentinel.account.security')->not->toContain('logout')
        ->and(array_column($registrar->conflicts(), 'reason', 'name'))->toBe(['logout' => 'uri', 'sentinel.account.security' => 'name', 'sentinel.panels.teacher.login' => 'uri']);
});

it('registers the Livewire components and middleware aliases', function (): void {
    foreach (['gcego-sentinel.profile-information', 'gcego-sentinel.update-password', 'gcego-sentinel.two-factor', 'gcego-sentinel.passkeys', 'gcego-sentinel.browser-sessions'] as $name) {
        expect(app('livewire.finder')->resolveClassComponentClassName($name))->toStartWith('Gcego\\Sentinel\\Livewire\\');
    }

    expect(app('router')->getMiddleware())->toHaveKeys(['sentinel.active', 'sentinel.two-factor', 'sentinel.registration', 'sentinel.session', 'sentinel.step-up']);
});

it('resolves login pages for panel paths', function (): void {
    $sentinel = app(SentinelManager::class);

    expect($sentinel->loginUrl(Request::create('/admin/users')))->toBe('/admin/login')
        ->and($sentinel->loginUrl(Request::create('/institution/2/classes')))->toBe('/institution/2/login')
        ->and($sentinel->loginUrl(Request::create('/dashboard')))->toBe('/login');
});

it('can be disabled entirely', function (): void {
    $this->reboot(['gcego-sentinel.enabled' => false]);

    expect(app('router')->getRoutes()->hasNamedRoute('sentinel.account.security'))->toBeFalse()
        ->and(app('router')->getRoutes()->hasNamedRoute('login.store'))->toBeFalse()
        ->and(app('router')->getMiddlewareGroups()['web'] ?? [])->not->toContain(EnforceSessionSecurity::class);
});
