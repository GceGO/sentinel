<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\Tests\Fixtures\Actions\CreateNewUser;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Gcego\Sentinel\Tests\Fixtures\TenantResolver;
use Gcego\Sentinel\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * A demo application for trying Sentinel in a browser: `composer serve`
 * (vendor/bin/testbench serve). Same configuration as the test suite.
 */
final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'app.name' => 'GCEGO Studio',
            'database.default' => 'workbench',
            'database.connections.workbench' => ['driver' => 'sqlite', 'database' => dirname(__DIR__, 2).'/database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'file',
            'auth.providers.users.model' => User::class,
            'session.driver' => 'database',
            'gcego-sentinel.home' => '/dashboard',
            'gcego-sentinel.features.passkeys' => true,
            'passkeys.relying_party_id' => 'localhost',
            'passkeys.allowed_origins' => ['http://localhost:8000', 'http://127.0.0.1:8000'],
            'gcego-security.tenancy' => ['enabled' => true, 'resolver' => TenantResolver::class, 'parameter' => 'tenant'],
            'gcego-sentinel.account_states.attribute' => 'status',
            'gcego-sentinel.branding' => ['name' => 'GCEGO Studio', 'tagline' => 'Past papers, practice and progress for every GCE candidate.', 'legal' => ['Privacy' => '/privacy', 'Terms' => '/terms'], 'support_email' => 'support@gcego.test'],
            'gcego-sentinel.panels' => [
                'admin' => ['path' => 'admin', 'title' => 'Administration', 'permission' => 'panels.admin'],
                'teacher' => ['path' => 'teacher', 'title' => 'Teachers', 'permission' => 'panels.teacher', 'layout' => 'centered'],
                'institution' => ['path' => 'institution/{tenant}', 'title' => 'Institution', 'permission' => 'institution.access', 'layout' => 'branded'],
                'student' => ['path' => 'student', 'title' => 'Students', 'permission' => 'panels.student', 'registration' => true, 'layout' => 'minimal'],
            ],
        ]);

        $this->app->singleton(CreatesAccounts::class, CreateNewUser::class);
    }

    public function boot(): void
    {
        foreach (TestCase::Permissions as $permission => $roles) {
            Gate::define($permission, static fn (User $user): bool => in_array($user->role, $roles, true));
        }
    }
}
