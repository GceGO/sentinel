<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Sentinel
|--------------------------------------------------------------------------
|
| Sentinel owns authentication end to end: sign-in, registration, password
| recovery, email verification, two-factor authentication, passkeys,
| sessions and account security. It is built on Laravel's own guards,
| user providers, hashing, password brokers, signed URLs, sessions and
| encryption; it never implements cryptography of its own.
|
| Every value is plain data, so this file works with `php artisan config:cache`.
| Invalid values stop the application at boot (fail closed); see
| docs/sentinel/CONFIGURATION.md for every option.
|
*/

return [

    'enabled' => true,

    /*
    | The session guard and password broker Sentinel authenticates with
    | (config/auth.php). The guard must be a session (stateful) guard.
    */
    'guard' => 'web',

    'password_broker' => 'users',

    /*
    | The sign-in identifier: the user attribute people type to sign in
    | ("email" or "username"). Lowercased before lookup when "lowercase" is
    | true (store identifiers lowercased too).
    */
    'identifier' => [
        'field' => 'email',
        'lowercase' => true,
    ],

    /*
    | Authentication capabilities. A disabled feature has no routes, no
    | pages and no buttons.
    */
    'features' => [
        'password_reset' => true,
        'email_verification' => true,
        'two_factor' => true,
        // WebAuthn passkeys need laravel/passkeys' "passkeys" table and a
        // user model implementing Laravel\Passkeys\Contracts\PasskeyUser.
        'passkeys' => false,
        'profile' => true,
        'password_update' => true,
        // "Keep me signed in" (remember-me cookie).
        'remember' => true,
    ],

    /*
    | Which Sentinel pages are shown. false leaves the page (and its GET
    | route) to the application.
    */
    'pages' => [
        'login' => true,
        'register' => true,
        'forgot_password' => true,
        'reset_password' => true,
        'verify_email' => true,
        'confirm_password' => true,
        'two_factor_challenge' => true,
        'account_security' => true,
    ],

    /*
    | Page view overrides: page key => view name, e.g. 'login' => 'auth.login'.
    | The view receives the same $context as Sentinel's own page.
    */
    'views' => [],

    'routes' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['web'],
        'account_security' => 'account/security',
        'account_suspended' => 'account/unavailable',
        'account_recovery' => 'account/recovery',
        'session_expired' => 'session-expired',
        'logged_out' => 'signed-out',
        // Served stylesheet and script (assets.mode = "package").
        'assets' => 'gcego-sentinel/assets',
    ],

    /*
    | Where people go after signing in when no intended URL and no panel apply,
    | and after signing out (null: the signed-out page).
    */
    'home' => '/',

    'logout_redirect' => null,

    'appearance' => [
        // centered | split | minimal | branded, or a layout registered in "layouts".
        'layout' => 'split',
        // system | light | dark
        'theme' => 'system',
        // Show the brand panel (logo, name, tagline) in layouts that have one.
        'branding' => true,
    ],

    /*
    | Custom layouts: name => Blade view. The view receives $context and $slot.
    */
    'layouts' => [],

    /*
    | Trusted branding. URLs must be relative ("/images/logo.svg") or https;
    | colors must be hex. Text is always escaped.
    */
    'branding' => [
        'name' => null,
        'tagline' => null,
        'logo' => null,
        'logo_dark' => null,
        'favicon' => null,
        'background' => null,
        'illustration' => null,
        'accent' => null,
        'footer' => null,
        // label => URL
        'legal' => [],
        'support_url' => null,
        'support_email' => null,
    ],

    /*
    | Registration is off unless enabled here (public site) or on a panel.
    | A custom policy (Contracts\RegistrationPolicy) can allow invitation-only
    | registration; a custom creator (Contracts\CreatesAccounts) decides the
    | stored attributes. New accounts never receive roles from Sentinel.
    */
    'registration' => [
        'enabled' => false,
        'policy' => null,
        'creator' => null,
        // When set, a "terms" checkbox is required and checked server-side.
        'terms_url' => null,
        // Extra fields passed to the account creator:
        // name => ['label' => '...', 'type' => 'text', 'autocomplete' => 'off', 'required' => false]
        'fields' => [],
    ],

    /*
    | Password policy (NIST SP 800-63B: length over composition rules).
    | Passwords longer than the hasher accepts are refused, never truncated
    | (bcrypt reads at most 72 bytes). "uncompromised" checks the Have I Been
    | Pwned range API (k-anonymity: only 5 characters of a SHA-1 prefix
    | leave the server) and needs outbound HTTPS.
    */
    'passwords' => [
        'min_length' => 12,
        'max_length' => 128,
        'uncompromised' => false,
        'uncompromised_threshold' => 0,
        // Seconds a password confirmation stays valid (null: auth.password_timeout).
        'confirmation_timeout' => null,
    ],

    /*
    | Two-factor authentication (TOTP, RFC 6238) and recovery codes.
    */
    'two_factor' => [
        // Users holding any of these permissions (gcego/security) must enroll.
        'required_permissions' => [],
        // Accepted 30-second steps either side of now (clock drift tolerance).
        'window' => 1,
        // Shown in authenticator apps (null: the branding name or app.name).
        'issuer' => null,
        'recovery_codes' => 8,
        // Seconds a password-verified sign-in may wait at the code challenge.
        'challenge_timeout' => 300,
        // Wrong codes allowed for one pending sign-in before the password is asked again.
        'challenge_attempts' => 5,
    ],

    'passkeys' => [
        // A passkey sign-in (user verification required) satisfies required
        // two-factor authentication: it is possession plus biometric or PIN.
        'satisfies_two_factor' => true,
        'max_per_user' => 10,
    ],

    'account_states' => [
        // With an attribute, users whose attribute matches a "suspended" or
        // "disabled" value cannot sign in, and their sessions end on their next
        // request. A custom resolver implements Contracts\AccountStateResolver.
        'resolver' => null,
        'attribute' => null,
        'suspended' => ['suspended'],
        'disabled' => ['disabled'],
        // Values meaning "pending security review" (signed out like suspended).
        'review' => ['pending_review'],
    ],

    'profile' => [
        // Fields shown by the profile form (passed to Contracts\UpdatesProfiles).
        'fields' => [
            'name' => ['label' => 'gcego-sentinel::messages.fields.name', 'type' => 'text', 'autocomplete' => 'name'],
            'email' => ['label' => 'gcego-sentinel::messages.fields.email', 'type' => 'email', 'autocomplete' => 'email'],
        ],
        'updater' => null,
    ],

    'sessions' => [
        // List signed-in devices on the account page (database session driver).
        'enabled' => true,
        // End a signed-in session after this many idle minutes (null: session.lifetime only).
        'idle_timeout' => null,
        // End a signed-in session this many minutes after sign-in, whatever the activity (null: never).
        'absolute_timeout' => null,
        // Sign every session out when the account's password changes (all session drivers).
        'bind_to_password' => true,
    ],

    /*
    | Rate limits: name => [attempts, decay seconds]. Keys combine the
    | account identifier (hashed) and the client address so one attacker
    | cannot lock a person out from everywhere. Use a shared cache store
    | (redis, database, memcached) when running more than one server.
    */
    'rate_limits' => [
        'enabled' => true,
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
    ],

    /*
    | Security audit trail. Events carry identifiers and outcomes only,
    | never passwords, codes, tokens or session ids. A custom sink
    | implements Contracts\SecurityAuditSink.
    */
    'audit' => [
        'enabled' => true,
        // database | log | null, or a class implementing Contracts\SecurityAuditSink.
        'sink' => 'database',
        'log_channel' => null,
        // full | truncated (IPv4 /24, IPv6 /48) | none
        'ip_addresses' => 'truncated',
        'user_agents' => true,
        // Pruned by `php artisan model:prune` (schedule it daily).
        'retention_days' => 365,
    ],

    /*
    | Security notifications (Laravel notifications, queued after commit).
    | They never contain passwords, codes, tokens or links that act on the account.
    */
    'notifications' => [
        'password_changed' => true,
        'two_factor_changed' => true,
        'recovery_code_used' => true,
        'passkeys_changed' => true,
        'email_changed' => true,
        'security_hold' => true,
        // Sign-ins from a browser this account has not used before.
        'new_sign_in' => false,
        'channels' => ['mail'],
    ],

    /*
    | Render friendly pages for expired sessions (419) and lockouts (429) on
    | authentication routes. Other routes keep the application's handling.
    */
    'errors' => [
        'session_expired' => true,
        'lockout' => true,
    ],

    /*
    | Panels: id => settings. Each gets a login page at /{path}/login. A path
    | containing "{tenant}" makes a tenant panel (login at
    | /institution/{tenant}/login, membership verified through gcego/security).
    |
    | 'admin' => [
    |     'path' => 'admin',
    |     'title' => 'Administration',
    |     'home' => null,                 // default "/{path}"
    |     'permission' => 'panels.admin', // gcego/security permission; tenant panels check it in the tenant
    |     'registration' => false,
    |     'two_factor' => false,          // true: members must enroll before entering
    |     'layout' => null,
    |     'theme' => null,
    |     'logout_redirect' => null,
    |     'branding' => [],
    | ],
    */
    'panels' => [],

    'database' => [
        'users_table' => 'users',
    ],

    'assets' => [
        // package: Sentinel serves its prebuilt stylesheet and script.
        // vite: the application's Vite build includes resources/css/sentinel.css
        //       and resources/js/sentinel.js; list the entry points here.
        'mode' => 'package',
        'vite' => [],
    ],

];
