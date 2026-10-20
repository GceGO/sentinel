<?php

declare(strict_types=1);

namespace Gcego\Sentinel;

use Gcego\Chroma\ChromaManager;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Sentinel\Accounts\AccountAccess;
use Gcego\Sentinel\Accounts\AttributeAccountStateResolver;
use Gcego\Sentinel\Accounts\SecurityHolds;
use Gcego\Sentinel\Audit\DatabaseAuditSink;
use Gcego\Sentinel\Audit\LogAuditSink;
use Gcego\Sentinel\Audit\NullAuditSink;
use Gcego\Sentinel\Audit\SecurityAuditRecorder;
use Gcego\Sentinel\Authentication\CredentialAuthenticator;
use Gcego\Sentinel\Authentication\LoginPipeline;
use Gcego\Sentinel\Authentication\LoginThrottle;
use Gcego\Sentinel\Branding\ConfiguredBrandingResolver;
use Gcego\Sentinel\Commands\HoldAccountCommand;
use Gcego\Sentinel\Commands\InspectSentinelCommand;
use Gcego\Sentinel\Commands\InstallSentinelCommand;
use Gcego\Sentinel\Commands\ReleaseAccountCommand;
use Gcego\Sentinel\Commands\ResetTwoFactorCommand;
use Gcego\Sentinel\Context\SentinelContextResolver;
use Gcego\Sentinel\Contracts\AccountStateResolver;
use Gcego\Sentinel\Contracts\AuthenticationBrandingResolver;
use Gcego\Sentinel\Contracts\AuthenticationContextResolver;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\Contracts\PanelAuthorizer;
use Gcego\Sentinel\Contracts\RegistrationPolicy;
use Gcego\Sentinel\Contracts\RiskAssessor;
use Gcego\Sentinel\Contracts\SecurityAuditSink;
use Gcego\Sentinel\Contracts\UpdatesProfiles;
use Gcego\Sentinel\Diagnostics\AuthenticationHealthCheck;
use Gcego\Sentinel\Diagnostics\SentinelInspector;
use Gcego\Sentinel\Events\SecurityEvent;
use Gcego\Sentinel\Features\ConfiguredFeatureResolver;
use Gcego\Sentinel\Http\AuthenticationErrors;
use Gcego\Sentinel\Http\Middleware\EnforceSessionSecurity;
use Gcego\Sentinel\Http\Middleware\EnsureAccountIsActive;
use Gcego\Sentinel\Http\Middleware\EnsureRegistrationIsAllowed;
use Gcego\Sentinel\Http\Middleware\EnsureTwoFactorIsEnabled;
use Gcego\Sentinel\Http\Middleware\RequireStepUp;
use Gcego\Sentinel\Layouts\LayoutManager;
use Gcego\Sentinel\Livewire\BrowserSessions;
use Gcego\Sentinel\Livewire\PasskeyManagement;
use Gcego\Sentinel\Livewire\ProfileInformationForm;
use Gcego\Sentinel\Livewire\TwoFactorManagement;
use Gcego\Sentinel\Livewire\UpdatePasswordForm;
use Gcego\Sentinel\MultiFactor\RecoveryCodes;
use Gcego\Sentinel\MultiFactor\TotpProvider;
use Gcego\Sentinel\MultiFactor\TotpReplayGuard;
use Gcego\Sentinel\MultiFactor\TwoFactorAuthentication;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\Panels\ConfiguredPanels;
use Gcego\Sentinel\Panels\SecurityPanelAuthorizer;
use Gcego\Sentinel\Passkeys\PasskeyAuthentication;
use Gcego\Sentinel\Passkeys\PasskeySupport;
use Gcego\Sentinel\Passwords\PasswordChanger;
use Gcego\Sentinel\Passwords\PasswordPolicy;
use Gcego\Sentinel\Passwords\PasswordResets;
use Gcego\Sentinel\Presets\SentinelStyles;
use Gcego\Sentinel\Profile\DefaultProfileUpdater;
use Gcego\Sentinel\Redirects\SafeRedirectValidator;
use Gcego\Sentinel\Redirects\SentinelRedirectResolver;
use Gcego\Sentinel\Registration\ConfiguredRegistrationPolicy;
use Gcego\Sentinel\Registration\DefaultAccountCreator;
use Gcego\Sentinel\Risk\ConservativeRiskAssessor;
use Gcego\Sentinel\Risk\KnownBrowsers;
use Gcego\Sentinel\Routing\SentinelRouteRegistrar;
use Gcego\Sentinel\Security\AuthenticationRateLimits;
use Gcego\Sentinel\Security\StepUp;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\Assets;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Log\LogManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/sentinel; also the "gcego/sentinel" GCEGO package.
 *
 * register(): configuration (validated: an invalid value stops the
 * application), services and contracts; laravel/passkeys' own routes are
 * switched off (Sentinel serves the ceremonies through its login pipeline).
 * boot(): views, translations, Blade and Livewire components, migrations,
 * middleware aliases and the session middleware on the "web" group,
 * presets, the audit listener and, once the application has booted, the
 * rate limiters and Sentinel's routes (never when routes are cached, never
 * over an existing route). Booting never queries the database.
 */
final class SentinelServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-sentinel';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-sentinel.php', SentinelConfig::Key);

        if (class_exists(Passkeys::class)) {
            Passkeys::ignoreRoutes();
        }

        $this->app->singleton(SentinelConfig::class, static function (Application $app): SentinelConfig {
            $config = $app->make(Repository::class)->get(SentinelConfig::Key, []);

            return SentinelConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([SentinelManager::class, LayoutManager::class, SentinelRouteRegistrar::class, Assets::class, SafeRedirectValidator::class, TotpProvider::class, PasswordPolicy::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request: they read the session, the request or the current user.
        foreach ([
            SentinelContextResolver::class, SentinelStyles::class, SessionRepository::class, SessionSecurity::class, TwoFactorPolicy::class,
            AccountAccess::class, SecurityHolds::class, TwoFactorAuthentication::class, TotpReplayGuard::class, RecoveryCodes::class,
            PasskeyAuthentication::class, CredentialAuthenticator::class, LoginThrottle::class, LoginPipeline::class, StepUp::class,
            PasswordChanger::class, PasswordResets::class, SecurityNotifier::class, KnownBrowsers::class,
        ] as $scoped) {
            $this->app->scoped($scoped);
        }

        $config = fn (Application $app): SentinelConfig => $app->make(SentinelConfig::class);
        $this->app->singleton(AuthenticationPanels::class, ConfiguredPanels::class);
        $this->app->scoped(AuthenticationFeatureResolver::class, ConfiguredFeatureResolver::class);
        $this->app->scoped(AuthenticationBrandingResolver::class, ConfiguredBrandingResolver::class);
        $this->app->scoped(AuthenticationContextResolver::class, SentinelContextResolver::class);
        $this->app->scoped(AuthenticationRedirectResolver::class, SentinelRedirectResolver::class);
        $this->app->scoped(PanelAuthorizer::class, SecurityPanelAuthorizer::class);
        $this->app->scoped(RiskAssessor::class, ConservativeRiskAssessor::class);
        $this->app->scoped(RegistrationPolicy::class, static function (Application $app) use ($config): RegistrationPolicy {
            $policy = $app->make($config($app)->registrationPolicy ?? ConfiguredRegistrationPolicy::class);

            return $policy instanceof RegistrationPolicy ? $policy : new ConfiguredRegistrationPolicy($config($app));
        });
        $this->app->scoped(AccountStateResolver::class, static function (Application $app) use ($config): AccountStateResolver {
            $resolver = $app->make($config($app)->accountStateResolver ?? AttributeAccountStateResolver::class);

            return $resolver instanceof AccountStateResolver ? $resolver : new AttributeAccountStateResolver($config($app));
        });
        $this->app->bindIf(CreatesAccounts::class, static function (Application $app) use ($config): CreatesAccounts {
            $creator = $app->make($config($app)->registrationCreator ?? DefaultAccountCreator::class);

            return $creator instanceof CreatesAccounts ? $creator : $app->make(DefaultAccountCreator::class);
        });
        $this->app->bindIf(UpdatesProfiles::class, static function (Application $app) use ($config): UpdatesProfiles {
            $updater = $app->make($config($app)->profileUpdater ?? DefaultProfileUpdater::class);

            return $updater instanceof UpdatesProfiles ? $updater : $app->make(DefaultProfileUpdater::class);
        });
        $this->app->singletonIf(SecurityAuditSink::class, static function (Application $app) use ($config): SecurityAuditSink {
            $settings = $config($app);
            $sink = match ($settings->auditSink) {
                'database' => new DatabaseAuditSink,
                'log' => new LogAuditSink($app->make(LogManager::class), $settings->auditLogChannel),
                'null' => new NullAuditSink,
                default => $app->make($settings->auditSink),
            };

            return $sink instanceof SecurityAuditSink ? $sink : new NullAuditSink;
        });
        $this->app->singleton(SecurityAuditRecorder::class);
        $this->app->singleton(SentinelInspector::class);
        $this->app->singleton(AuthenticationHealthCheck::class);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        $this->loadMigrationsFrom($root.'/database/migrations');
        Blade::anonymousComponentPath($root.'/resources/views/components', self::Views);

        $this->publishes([$root.'/config/gcego-sentinel.php' => $this->app->configPath('gcego-sentinel.php')], 'gcego-sentinel-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-sentinel-views');
        $this->publishes([$root.'/resources/lang' => $this->app->langPath('vendor/'.self::Views)], 'gcego-sentinel-lang');
        $this->publishesMigrations([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-sentinel-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallSentinelCommand::class, InspectSentinelCommand::class, HoldAccountCommand::class, ReleaseAccountCommand::class, ResetTwoFactorCommand::class]);
        }

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('sentinel.active', EnsureAccountIsActive::class);
        $router->aliasMiddleware('sentinel.two-factor', EnsureTwoFactorIsEnabled::class);
        $router->aliasMiddleware('sentinel.registration', EnsureRegistrationIsAllowed::class);
        $router->aliasMiddleware('sentinel.session', EnforceSessionSecurity::class);
        $router->aliasMiddleware('sentinel.step-up', RequireStepUp::class);

        SentinelStyles::register($this->app->make(ChromaManager::class));

        Livewire::component('gcego-sentinel.profile-information', ProfileInformationForm::class);
        Livewire::component('gcego-sentinel.update-password', UpdatePasswordForm::class);
        Livewire::component('gcego-sentinel.two-factor', TwoFactorManagement::class);
        Livewire::component('gcego-sentinel.passkeys', PasskeyManagement::class);
        Livewire::component('gcego-sentinel.browser-sessions', BrowserSessions::class);
        Livewire::addPersistentMiddleware([EnsureAccountIsActive::class]);

        $config = $this->app->make(SentinelConfig::class);

        if (! $config->enabled) {
            return;
        }

        $router->pushMiddlewareToGroup('web', EnforceSessionSecurity::class);
        $this->app->make(Dispatcher::class)->listen(SecurityEvent::class, [SecurityAuditRecorder::class, 'handle']);
        $this->app->make(AuthenticationErrors::class)->register($this->app->make(ExceptionHandler::class));

        if (class_exists(Passkeys::class) && is_string($model = PasskeySupport::userModel($this->app->make(Repository::class), $config->guard)) && is_a($model, PasskeyUser::class, true)) {
            Passkeys::useUserModel($model);
        }

        $this->app->booted(function () use ($config): void {
            $events = $this->app->make(Dispatcher::class);

            // Laravel's default event provider sends verification links on Registered; add it only where nothing listens.
            if ($config->emailVerification && ! $events->hasListeners(Registered::class)) {
                $events->listen(Registered::class, SendEmailVerificationNotification::class);
            }

            if ($config->rateLimiters) {
                $this->app->make(AuthenticationRateLimits::class)->register();
            }

            if (! ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
                $this->app->make(SentinelRouteRegistrar::class)->register($this->app->make(Router::class));
            }
        });
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/sentinel',
            requires: ['gcego/core', 'gcego/security', 'gcego/chroma'],
            capabilities: [
                'authentication.pages', 'authentication.engine', 'authentication.account-security', 'authentication.two-factor',
                'authentication.passkeys', 'authentication.sessions', 'authentication.panels', 'authentication.audit',
            ],
            description: 'Independent authentication: sign-in, registration, recovery, verification, TOTP two-factor, passkeys, sessions, step-up, holds, audit and panel-aware redirects.',
        );
    }
}
