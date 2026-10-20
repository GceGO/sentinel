# gcego/sentinel

Independent authentication and account security for GCEGO Admin Studio, built on Laravel's own guards, hashing, password brokers, signed URLs, sessions and encryption.

- **Sign-in engine:** credential sign-in with layered throttling and generic errors; one login pipeline for every method (password, TOTP, recovery code, passkey) that enforces account states, panel and tenant access, and two-factor policy.
- **Recovery and verification:** password reset through Laravel's broker (all sessions end), signed email verification, password confirmation and `sentinel.step-up` routes.
- **Multi-factor:** RFC 6238 TOTP with replay protection, recovery codes stored only as hashes, and passkeys (WebAuthn, through laravel/passkeys).
- **Sessions:** idle and absolute timeouts, sign-out on password change and "sign out everywhere" on every session driver, device list and revocation.
- **Account security:** account states, administrative security holds, risk signals, an audit trail and security notifications.
- **Pages:** login, registration, recovery, verification, confirmation, two-factor challenge and account recovery, plus the Livewire account security center.
- **Panel-aware sign-in:** `/admin/login`, `/institution/{tenant}/login`, with panel and tenant access checked through **gcego/security**.
- **Presentation:** four layouts, light, dark and system themes, trusted branding, and English and French.

```bash
composer require gcego/sentinel
php artisan migrate
php artisan gcego:sentinel-install
```

The full documentation set is in `docs/sentinel/` at the repository root: architecture, flows, security model, configuration, MFA and passkeys, deployment hardening, and the test report.

## Requirements

- PHP 8.3+, Laravel 13, Livewire 4.4+
- gcego/core, gcego/security and gcego/chroma; pragmarx/google2fa, bacon/bacon-qr-code and laravel/passkeys (installed with Sentinel)
- A session guard and a shared cache store in multi-server deployments

## Minimal configuration

<!-- example:config -->
```php
// config/gcego-sentinel.php (published by the installer)
return [
    'appearance' => ['layout' => 'split', 'theme' => 'system'],
    'branding' => ['name' => 'GCEGO', 'accent' => '#1d4ed8', 'legal' => ['Privacy' => '/privacy']],
    'registration' => ['enabled' => false],
    'account_states' => ['attribute' => 'status'],
    'panels' => [
        'admin' => ['path' => 'admin', 'title' => 'Administration', 'permission' => 'panels.admin', 'two_factor' => true],
        'teacher' => ['path' => 'teacher', 'title' => 'Teachers', 'permission' => 'panels.teacher'],
        'institution' => ['path' => 'institution/{tenant}', 'title' => 'Institution', 'permission' => 'institution.access'],
    ],
];
```

With this configuration:

| URL | What it is |
| --- | --- |
| `/login`, `/logout`, `/forgot-password`, `/reset-password/{token}`, `/email/verify`, `/user/confirm-password`, `/two-factor-challenge`, `/register`, `/passkeys/login` | Sentinel's authentication endpoints (the usual Laravel route names) |
| `/admin/login`, `/teacher/login`, `/institution/{tenant}/login` | Each panel's own branded login page (registered only when the URL is free) |
| `/account/security` | The account security center |
| `/account/unavailable`, `/account/recovery`, `/session-expired`, `/signed-out` | Informational pages |

- **Admin panel:** a user who signs in at `/admin/login` without the `panels.admin` permission is signed out again and told so. Admins without two-factor authentication are sent to enroll first.
- **Institution panel:** members of institution 42 land on `/institution/42`, but only after gcego/security has verified their membership and the tenant permission `institution.access`.

## Documentation

| Topic | Page |
| --- | --- |
| Installation, assets, deployment | [docs/installation.md](docs/installation.md) |
| Architecture, flows, security model, configuration, MFA, passkeys, hardening (authoritative) | [../../docs/sentinel/](../../docs/sentinel/ARCHITECTURE.md) |
| Pages, redirects and account states | [docs/pages.md](docs/pages.md) |
| Layouts, branding, themes, Tailwind CSS v4, accessibility | [docs/layouts.md](docs/layouts.md) |
| Panels, tenants and multiple guards | [docs/panels.md](docs/panels.md) |
| Registration policies | [docs/registration.md](docs/registration.md) |
| Two-factor authentication and recovery codes | [docs/two-factor.md](docs/two-factor.md) |
| Account security center and browser sessions | [docs/sessions.md](docs/sessions.md) |
| Security model | [docs/security.md](docs/security.md) |
| Customization, localization, testing, troubleshooting | [docs/customization.md](docs/customization.md) |
| API reference | [docs/api-reference.md](docs/api-reference.md) |

## Development

```bash
composer install
composer test           # Pest
composer analyse        # PHPStan (max)
composer format         # Pint
composer build-assets   # rebuild resources/dist (Tailwind CSS v4)
composer serve          # demo app in the browser (Testbench workbench)
```

`testbench serve` writes a `.env` into Testbench's skeleton, which changes the test environment; `composer test` deletes it first.

The demo app seeds `admin@gcego.test`, `teacher@gcego.test`, `student@gcego.test`, `member@gcego.test` (institution 1) and `suspended@gcego.test`, all with the password `password`.
