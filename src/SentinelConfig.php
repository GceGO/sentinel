<?php

declare(strict_types=1);

namespace Gcego\Sentinel;

use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Contracts\AccountStateResolver;
use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\Contracts\RegistrationPolicy;
use Gcego\Sentinel\Contracts\SecurityAuditSink;
use Gcego\Sentinel\Contracts\UpdatesProfiles;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Layouts\LayoutManager;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Support\ConfigReader;
use Gcego\Sentinel\Support\FormField;

/**
 * Validated gcego-sentinel configuration (a container singleton, built once
 * per application; plain data, so config:cache works).
 */
final readonly class SentinelConfig
{
    public const string Key = 'gcego-sentinel';

    private const string ViewPattern = '/^[A-Za-z0-9_.:\/-]{1,190}$/';

    private const string MiddlewarePattern = '/^[A-Za-z0-9_.:,\\\\-]{1,190}$/';

    private const array ReservedRegistrationFields = ['name', 'email', 'password', 'password_confirmation', 'terms', 'remember', '_token'];

    /**
     * Rate limiter name => default [attempts, decay seconds].
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const array RateLimits = [
        'login' => [5, 60],
        'login_ip' => [30, 60],
        'login_account' => [50, 3600],
        'two_factor' => [5, 60],
        'password_reset_request' => [5, 60],
        'password_reset_request_ip' => [30, 3600],
        'password_reset' => [5, 60],
        'verification' => [6, 60],
        'confirm_password' => [5, 60],
        'register' => [5, 60],
        'register_ip' => [30, 3600],
        'passkeys' => [10, 60],
    ];

    /**
     * Notification switch => default.
     *
     * @var array<string, bool>
     */
    public const array Notifications = [
        'password_changed' => true,
        'two_factor_changed' => true,
        'recovery_code_used' => true,
        'passkeys_changed' => true,
        'email_changed' => true,
        'security_hold' => true,
        'new_sign_in' => false,
    ];

    /**
     * @param  array<string, bool>  $pages  page key => enabled
     * @param  array<string, string>  $views  page key => view override
     * @param  list<string>  $middleware
     * @param  array<string, string>  $layouts  name => view
     * @param  list<FormField>  $registrationFields
     * @param  list<string>  $suspendedValues
     * @param  list<string>  $disabledValues
     * @param  list<string>  $reviewValues
     * @param  list<string>  $twoFactorPermissions
     * @param  list<FormField>  $profileFields
     * @param  array<string, AuthenticationPanel>  $panels
     * @param  list<string>  $vite
     * @param  array<string, array{0: int, 1: int}>  $rateLimits
     * @param  array<string, bool>  $notifications
     * @param  list<string>  $notificationChannels
     * @param  class-string<RegistrationPolicy>|null  $registrationPolicy
     * @param  class-string<CreatesAccounts>|null  $registrationCreator
     * @param  class-string<UpdatesProfiles>|null  $profileUpdater
     * @param  class-string<AccountStateResolver>|null  $accountStateResolver
     */
    public function __construct(
        public bool $enabled = true,
        public string $guard = 'web',
        public string $passwordBroker = 'users',
        public string $identifier = 'email',
        public bool $lowercaseIdentifier = true,
        public bool $passwordReset = true,
        public bool $emailVerification = true,
        public bool $twoFactor = true,
        public bool $passkeys = false,
        public bool $profile = true,
        public bool $passwordUpdate = true,
        public bool $remember = true,
        public array $pages = [],
        public array $views = [],
        public bool $routes = true,
        public string $prefix = '',
        public array $middleware = ['web'],
        public string $accountSecurityPath = 'account/security',
        public string $accountUnavailablePath = 'account/unavailable',
        public string $accountRecoveryPath = 'account/recovery',
        public string $sessionExpiredPath = 'session-expired',
        public string $loggedOutPath = 'signed-out',
        public string $assetsPath = 'gcego-sentinel/assets',
        public string $home = '/',
        public ?string $logoutRedirect = null,
        public string $layout = 'split',
        public string $theme = 'system',
        public bool $showBranding = true,
        public array $layouts = [],
        public AuthenticationBranding $branding = new AuthenticationBranding,
        public bool $registration = false,
        public ?string $registrationPolicy = null,
        public ?string $registrationCreator = null,
        public ?string $termsUrl = null,
        public array $registrationFields = [],
        public int $passwordMinLength = 12,
        public int $passwordMaxLength = 128,
        public bool $uncompromisedPasswords = false,
        public int $uncompromisedThreshold = 0,
        public ?int $confirmationTimeout = null,
        public ?string $accountStateResolver = null,
        public ?string $accountStateAttribute = null,
        public array $suspendedValues = ['suspended'],
        public array $disabledValues = ['disabled'],
        public array $reviewValues = ['pending_review'],
        public array $twoFactorPermissions = [],
        public int $twoFactorWindow = 1,
        public ?string $twoFactorIssuer = null,
        public int $recoveryCodeCount = 8,
        public int $challengeTimeout = 300,
        public int $challengeAttempts = 5,
        public bool $passkeysSatisfyTwoFactor = true,
        public int $maxPasskeys = 10,
        public array $profileFields = [],
        public ?string $profileUpdater = null,
        public bool $browserSessions = true,
        public ?int $idleTimeout = null,
        public ?int $absoluteTimeout = null,
        public bool $bindSessionsToPassword = true,
        public bool $rateLimiters = true,
        public array $rateLimits = self::RateLimits,
        public bool $audit = true,
        public string $auditSink = 'database',
        public ?string $auditLogChannel = null,
        public string $auditIpAddresses = 'truncated',
        public bool $auditUserAgents = true,
        public int $auditRetentionDays = 365,
        public array $notifications = self::Notifications,
        public array $notificationChannels = ['mail'],
        public bool $sessionExpiredPage = true,
        public bool $lockoutPage = true,
        public array $panels = [],
        public string $usersTable = 'users',
        public string $assetsMode = 'package',
        public array $vite = [],
    ) {
        foreach ($pages as $page => $enabled) {
            if (AuthenticationPage::tryFrom($page)?->isSwitchable() !== true) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.pages', sprintf('"%s" is not a Sentinel page.', mb_substr($page, 0, 60)));
            }
        }

        foreach ($views as $page => $view) {
            if (AuthenticationPage::tryFrom($page) === null || preg_match(self::ViewPattern, $view) !== 1) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.views', sprintf('"%s" must map a Sentinel page to a Blade view name.', mb_substr($page, 0, 60)));
            }
        }

        foreach ($middleware as $entry) {
            if (preg_match(self::MiddlewarePattern, $entry) !== 1) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.routes.middleware', sprintf('"%s" is not a middleware name or alias.', mb_substr($entry, 0, 60)));
            }
        }

        foreach (['guard' => $guard, 'password_broker' => $passwordBroker, 'database.users_table' => $usersTable] as $key => $name) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/', $name) !== 1) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.'.$key, 'use a name such as "web" or "users".');
            }
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $identifier) !== 1 || $identifier === 'password') {
            throw AuthenticationConfigurationException::invalid(self::Key.'.identifier.field', 'use a user attribute such as "email" or "username".');
        }

        if ($passwordMaxLength < $passwordMinLength) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.passwords.max_length', 'must not be lower than passwords.min_length.');
        }

        if (! in_array($auditIpAddresses, ['full', 'truncated', 'none'], true)) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.audit.ip_addresses', 'use "full", "truncated" or "none".');
        }

        if (! in_array($auditSink, ['database', 'log', 'null'], true) && ! is_a($auditSink, SecurityAuditSink::class, true)) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.audit.sink', 'use "database", "log", "null" or a class implementing '.SecurityAuditSink::class.'.');
        }

        foreach ($notificationChannels as $channel) {
            if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $channel) !== 1) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.notifications.channels', sprintf('"%s" is not a notification channel name.', mb_substr($channel, 0, 60)));
            }
        }

        foreach (['prefix' => $prefix, 'account_security' => $accountSecurityPath, 'account_suspended' => $accountUnavailablePath, 'account_recovery' => $accountRecoveryPath, 'session_expired' => $sessionExpiredPath, 'logged_out' => $loggedOutPath, 'assets' => $assetsPath] as $key => $path) {
            if (preg_match('/^(?:[a-z0-9_-]+(?:\/[a-z0-9_-]+)*)?$/', $path) !== 1 || ($key !== 'prefix' && $path === '')) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.routes.'.$key, 'use lowercase URL segments such as "account/security".');
            }
        }

        foreach (['home' => $home, 'logout_redirect' => $logoutRedirect] as $key => $url) {
            if ($url !== null && (! str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $url) === 1)) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.'.$key, 'use a root-relative path such as "/dashboard".');
            }
        }

        foreach ($layouts as $name => $view) {
            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $name) !== 1 || preg_match(self::ViewPattern, $view) !== 1) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.layouts', sprintf('"%s" must map a layout name to a Blade view.', mb_substr($name, 0, 60)));
            }
        }

        $layoutNames = [...LayoutManager::BuiltIn, ...array_keys($layouts)];

        if (! in_array($layout, $layoutNames, true)) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.appearance.layout', 'use '.implode(', ', $layoutNames).'.');
        }

        foreach ($panels as $panel) {
            if ($panel->layout !== null && ! in_array($panel->layout, $layoutNames, true)) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.panels.'.$panel->id.'.layout', 'use '.implode(', ', $layoutNames).'.');
            }
        }

        if (! in_array($theme, ['system', 'light', 'dark'], true)) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.appearance.theme', 'use "system", "light" or "dark".');
        }

        if ($termsUrl !== null && Support\SafeUrl::link($termsUrl) === null) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.registration.terms_url', 'use a relative or http(s) URL.');
        }

        foreach ($registrationFields as $field) {
            if (in_array($field->name, self::ReservedRegistrationFields, true)) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.registration.fields', sprintf('"%s" is a built-in registration field.', $field->name));
            }
        }

        if ($accountStateAttribute !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $accountStateAttribute) !== 1) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.account_states.attribute', 'use an attribute name such as "status".');
        }

        $paths = [];

        foreach ($panels as $panel) {
            $normalized = (string) preg_replace('/\{tenant\}/', '{}', $panel->path);

            if (isset($paths[$normalized])) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.panels.'.$panel->id.'.path', sprintf('panel "%s" already uses it.', $paths[$normalized]));
            }

            $paths[$normalized] = $panel->id;
        }

        if (! in_array($assetsMode, ['package', 'vite'], true)) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.assets.mode', 'use "package" or "vite".');
        }

        foreach ($vite as $entry) {
            if (preg_match('/^[A-Za-z0-9_.\/-]{1,190}$/', $entry) !== 1 || str_contains($entry, '..')) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.assets.vite', sprintf('"%s" is not a Vite entry path.', mb_substr($entry, 0, 60)));
            }
        }

        if ($assetsMode === 'vite' && $vite === []) {
            throw AuthenticationConfigurationException::invalid(self::Key.'.assets.vite', 'list the Vite entry points when assets.mode is "vite".');
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $read = new ConfigReader($config, self::Key);
        $pages = $read->section('pages');
        $identifier = $read->section('identifier');
        $features = $read->section('features');
        $routes = $read->section('routes');
        $appearance = $read->section('appearance');
        $registration = $read->section('registration');
        $passwords = $read->section('passwords');
        $twoFactor = $read->section('two_factor');
        $passkeys = $read->section('passkeys');
        $states = $read->section('account_states');
        $profile = $read->section('profile');
        $sessions = $read->section('sessions');
        $limits = $read->section('rate_limits');
        $audit = $read->section('audit');
        $notifications = $read->section('notifications');
        $assets = $read->section('assets');
        $pageFlags = [];

        foreach (AuthenticationPage::cases() as $page) {
            if ($page->isSwitchable()) {
                $pageFlags[$page->value] = $pages->bool($page->value, true);
            }
        }

        foreach (array_keys(is_array($config['pages'] ?? null) ? $config['pages'] : []) as $page) {
            if (! isset($pageFlags[$page])) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.pages', sprintf('"%s" is not a Sentinel page.', mb_substr((string) $page, 0, 60)));
            }
        }

        $panels = [];

        foreach ($read->sections('panels') as $id => $values) {
            $panels[$id] = AuthenticationPanel::fromArray($id, $values);
        }

        $rateLimits = [];

        foreach (self::RateLimits as $name => $default) {
            $rateLimits[$name] = $limits->limit($name, $default);
        }

        foreach (array_keys(is_array($config['rate_limits'] ?? null) ? $config['rate_limits'] : []) as $name) {
            if ($name !== 'enabled' && ! isset(self::RateLimits[$name])) {
                throw AuthenticationConfigurationException::invalid(self::Key.'.rate_limits', sprintf('"%s" is not a Sentinel rate limit.', mb_substr((string) $name, 0, 60)));
            }
        }

        $notify = [];

        foreach (self::Notifications as $name => $default) {
            $notify[$name] = $notifications->bool($name, $default);
        }

        $sink = $audit->string('sink', 'database');

        return new self(
            enabled: $read->bool('enabled', true),
            guard: $read->string('guard', 'web'),
            passwordBroker: $read->string('password_broker', 'users'),
            identifier: $identifier->string('field', 'email'),
            lowercaseIdentifier: $identifier->bool('lowercase', true),
            passwordReset: $features->bool('password_reset', true),
            emailVerification: $features->bool('email_verification', true),
            twoFactor: $features->bool('two_factor', true),
            passkeys: $features->bool('passkeys', false),
            profile: $features->bool('profile', true),
            passwordUpdate: $features->bool('password_update', true),
            remember: $features->bool('remember', true),
            pages: $pageFlags,
            views: $read->stringMap('views'),
            routes: $routes->bool('enabled', true),
            prefix: trim($routes->string('prefix', ''), '/'),
            middleware: $routes->strings('middleware', ['web']),
            accountSecurityPath: trim($routes->string('account_security', 'account/security'), '/'),
            accountUnavailablePath: trim($routes->string('account_suspended', 'account/unavailable'), '/'),
            accountRecoveryPath: trim($routes->string('account_recovery', 'account/recovery'), '/'),
            sessionExpiredPath: trim($routes->string('session_expired', 'session-expired'), '/'),
            loggedOutPath: trim($routes->string('logged_out', 'signed-out'), '/'),
            assetsPath: trim($routes->string('assets', 'gcego-sentinel/assets'), '/'),
            home: $read->string('home', '/'),
            logoutRedirect: $read->nullableString('logout_redirect'),
            layout: $appearance->string('layout', 'split'),
            theme: $appearance->string('theme', 'system'),
            showBranding: $appearance->bool('branding', true),
            layouts: $read->stringMap('layouts'),
            branding: AuthenticationBranding::fromArray(is_array($config['branding'] ?? null) ? $config['branding'] : [], self::Key.'.branding'),
            registration: $registration->bool('enabled', false),
            registrationPolicy: $registration->implementation('policy', RegistrationPolicy::class),
            registrationCreator: $registration->implementation('creator', CreatesAccounts::class),
            termsUrl: $registration->nullableString('terms_url'),
            registrationFields: self::fields($registration, 'fields'),
            passwordMinLength: $passwords->int('min_length', 12, 8, 256),
            passwordMaxLength: $passwords->int('max_length', 128, 8, 4096),
            uncompromisedPasswords: $passwords->bool('uncompromised', false),
            uncompromisedThreshold: $passwords->int('uncompromised_threshold', 0, 0, 1000000),
            confirmationTimeout: $passwords->nullableInt('confirmation_timeout', 60, 86400),
            accountStateResolver: $states->implementation('resolver', AccountStateResolver::class),
            accountStateAttribute: $states->nullableString('attribute'),
            suspendedValues: $states->strings('suspended', ['suspended']),
            disabledValues: $states->strings('disabled', ['disabled']),
            reviewValues: $states->strings('review', ['pending_review']),
            twoFactorPermissions: $twoFactor->strings('required_permissions'),
            twoFactorWindow: $twoFactor->int('window', 1, 0, 4),
            twoFactorIssuer: $twoFactor->nullableString('issuer'),
            recoveryCodeCount: $twoFactor->int('recovery_codes', 8, 4, 20),
            challengeTimeout: $twoFactor->int('challenge_timeout', 300, 60, 1800),
            challengeAttempts: $twoFactor->int('challenge_attempts', 5, 1, 20),
            passkeysSatisfyTwoFactor: $passkeys->bool('satisfies_two_factor', true),
            maxPasskeys: $passkeys->int('max_per_user', 10, 1, 100),
            profileFields: self::fields($profile, 'fields', [
                'name' => ['label' => 'gcego-sentinel::messages.fields.name', 'type' => 'text', 'autocomplete' => 'name'],
                'email' => ['label' => 'gcego-sentinel::messages.fields.email', 'type' => 'email', 'autocomplete' => 'email'],
            ]),
            profileUpdater: $profile->implementation('updater', UpdatesProfiles::class),
            browserSessions: $sessions->bool('enabled', true),
            idleTimeout: $sessions->nullableInt('idle_timeout', 1, 525600),
            absoluteTimeout: $sessions->nullableInt('absolute_timeout', 1, 525600),
            bindSessionsToPassword: $sessions->bool('bind_to_password', true),
            rateLimiters: $limits->bool('enabled', true),
            rateLimits: $rateLimits,
            audit: $audit->bool('enabled', true),
            auditSink: $sink,
            auditLogChannel: $audit->nullableString('log_channel'),
            auditIpAddresses: $audit->string('ip_addresses', 'truncated'),
            auditUserAgents: $audit->bool('user_agents', true),
            auditRetentionDays: $audit->int('retention_days', 365, 1, 3650),
            notifications: $notify,
            notificationChannels: $notifications->strings('channels', ['mail']),
            sessionExpiredPage: $read->section('errors')->bool('session_expired', true),
            lockoutPage: $read->section('errors')->bool('lockout', true),
            panels: $panels,
            usersTable: $read->section('database')->string('users_table', 'users'),
            assetsMode: $assets->string('mode', 'package'),
            vite: $assets->strings('vite'),
        );
    }

    /**
     * @param  array<string, array<array-key, mixed>>  $default  used when the section lists no fields
     * @return list<FormField>
     */
    private static function fields(ConfigReader $section, string $key, array $default = []): array
    {
        $definitions = $section->sections($key);
        $fields = [];

        foreach ($definitions === [] ? $default : $definitions as $name => $values) {
            $fields[] = FormField::fromArray($name, $values, $section->key($key));
        }

        return $fields;
    }

    public function pageEnabled(AuthenticationPage $page): bool
    {
        return ! $page->isSwitchable() || ($this->pages[$page->value] ?? true);
    }

    public function notifies(string $notification): bool
    {
        return $this->notifications[$notification] ?? false;
    }

    /**
     * @return array{0: int, 1: int} [attempts, decay seconds]
     */
    public function rateLimit(string $name): array
    {
        return $this->rateLimits[$name] ?? self::RateLimits[$name] ?? [5, 60];
    }

    public function panel(?string $id): ?AuthenticationPanel
    {
        return $id === null ? null : ($this->panels[$id] ?? null);
    }

    /**
     * A route path under the configured prefix ("account/security" -> "auth/account/security").
     */
    public function path(string $path): string
    {
        return ltrim($this->prefix.'/'.$path, '/');
    }
}
