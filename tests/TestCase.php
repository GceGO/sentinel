<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Data\DataServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\SentinelServiceProvider;
use Gcego\Sentinel\Testing\InteractsWithSentinel;
use Gcego\Sentinel\Tests\Fixtures\Actions\CreateNewUser;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Gcego\Sentinel\Tests\Fixtures\TenantResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint as Table;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\PasskeysServiceProvider;
use Livewire\LivewireServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use InteractsWithSentinel;

    /** Who holds which global permission (Gate abilities, gcego/security's "gate" provider), by role. */
    public const array Permissions = [
        'panels.admin' => ['admin'],
        'panels.teacher' => ['teacher'],
        'panels.student' => ['student'],
        'security.manage' => ['admin'],
    ];

    /**
     * Configuration applied on top of the defaults for the next application (see reboot()).
     *
     * @var array<string, mixed>
     */
    public static array $overrides = [];

    /**
     * Providers registered after the packages for the next application (an application's own providers).
     *
     * @var list<class-string>
     */
    public static array $applicationProviders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepare();
    }

    protected function tearDown(): void
    {
        self::$overrides = [];
        self::$applicationProviders = [];
        CreateNewUser::$received = [];
        TenantResolver::$lookups = 0;

        parent::tearDown();
    }

    /**
     * A fresh application with these configuration values (routes are
     * registered from configuration at boot, so they cannot change afterwards).
     *
     * @param  array<string, mixed>  $config
     */
    public function reboot(array $config): void
    {
        self::$overrides = $config;
        $this->refreshApplication();
        $this->prepare();
    }

    protected function getPackageProviders($app): array
    {
        $app->register(ComponentServiceProvider::class);

        return [LivewireServiceProvider::class, PasskeysServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class, DataServiceProvider::class, SecurityServiceProvider::class, BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeLucideIconsServiceProvider::class, LuminariesServiceProvider::class, SentinelServiceProvider::class, ...self::$applicationProviders];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('app.url', 'http://localhost');
        $config->set('app.name', 'GCEGO Studio');
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('mail.default', 'array');
        $config->set('hashing.bcrypt.rounds', 4);
        $config->set('auth.providers.users.model', User::class);
        $config->set('auth.passwords.users', ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]);
        $config->set('passkeys.relying_party_id', 'localhost');
        $config->set('passkeys.allowed_origins', ['http://localhost']);
        $config->set('passkeys.user_handle_secret', 'base64:'.base64_encode(str_repeat('h', 32)));
        $config->set('gcego-sentinel.home', '/dashboard');
        $config->set('gcego-sentinel.features.passkeys', true);
        $config->set('gcego-registry.discovery.enabled', false);
        $config->set('gcego-registry.cache.enabled', false);
        $config->set('gcego-security.tenancy', ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'tenant']);
        $config->set('gcego-sentinel.account_states.attribute', 'status');
        $config->set('gcego-sentinel.panels', [
            'admin' => ['path' => 'admin', 'title' => 'Administration', 'permission' => 'panels.admin'],
            'teacher' => ['path' => 'teacher', 'title' => 'Teachers', 'permission' => 'panels.teacher', 'layout' => 'centered'],
            'institution' => ['path' => 'institution/{tenant}', 'title' => 'Institution', 'permission' => 'institution.access'],
            'student' => ['path' => 'student', 'title' => 'Students', 'permission' => 'panels.student', 'registration' => true],
        ]);

        foreach (self::$overrides as $key => $value) {
            $config->set($key, $value);
        }

        $app->singleton(CreatesAccounts::class, CreateNewUser::class);

        foreach (self::Permissions as $permission => $roles) {
            Gate::define($permission, static fn (User $user): bool => in_array($user->role, $roles, true));
        }
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(static function (): void {
            Route::get('/dashboard', static fn (): string => 'Dashboard')->middleware('auth');
            Route::get('/dashboard/reports', static fn (): string => 'Reports')->middleware('auth');
            Route::get('/admin', static fn (): string => 'Admin home')->middleware('auth');
            Route::get('/admin/users', static fn (): string => 'Admin users')->middleware('auth');
            Route::get('/admin/secure', static fn (): string => 'Admin secure')->middleware(['auth', 'sentinel.two-factor:admin']);
            Route::get('/teacher', static fn (): string => 'Teacher home')->middleware('auth');
            Route::get('/student', static fn (): string => 'Student home')->middleware('auth');
            Route::get('/institution/{tenant}', static fn (string $tenant): string => 'Institution '.$tenant)->middleware('auth');
            Route::get('/institution/{tenant}/classes', static fn (string $tenant): string => 'Classes '.$tenant)->middleware('auth');
            Route::get('/verified-only', static fn (): string => 'Verified')->middleware(['auth', 'verified']);
            Route::get('/confirmed-only', static fn (): string => 'Confirmed')->middleware(['auth', 'password.confirm']);
        });
    }

    private function prepare(): void
    {
        // Each request gets fresh request-scoped services, as in production.
        $this->app?->make('events')->listen(RequestHandled::class, fn () => $this->app?->forgetScopedInstances());

        Schema::create('users', static function (Table $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->nullable();
            $table->string('status')->default('active');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', static function (Table $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', static function (Table $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('passkeys', static function (Table $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
        $migration = require dirname(__DIR__).'/database/migrations/2026_10_18_000001_create_sentinel_tables.php';

        if ($migration instanceof Migration && method_exists($migration, 'up')) {
            $migration->up();
        }
        Schema::create('memberships', static function (Table $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedInteger('institution_id');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });
    }
}
