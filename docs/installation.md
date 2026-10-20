# Installation

## 1. Install

```bash
composer require gcego/sentinel
php artisan migrate                           # Sentinel's tables (sentinel_*)
php artisan gcego:sentinel-install            # publish config/gcego-sentinel.php, then run the health check
php artisan gcego:sentinel-install --dry-run  # show what would change
php artisan gcego:sentinel-install --views    # also copy the Blade views (missing files only)
php artisan gcego:sentinel-install --lang     # also copy the translations (missing files only)
php artisan gcego:sentinel-install --migrations  # copy the migrations into database/migrations
php artisan gcego:sentinel-install --strict   # exit 1 when a health check fails (CI)
```

The installer is idempotent and non-interactive: it only creates files that do not exist yet. To check the setup at any time:

```bash
php artisan gcego:sentinel-inspect          # table
php artisan gcego:sentinel-inspect --json   # gcego/core DiagnosticReport format; exit 1 on failures
```

## 2. Requirements in the application

- **Guard:** a session guard (usually `web`, set in `gcego-sentinel.guard`).
- **Password broker:** `auth.passwords.users`, for password resets.
- **User table:** `two_factor_secret` (text, nullable) and `two_factor_confirmed_at` (timestamp, nullable) for two-factor authentication.
- **User model:**
  - implement `MustVerifyEmail` for email verification;
  - for passkeys, implement `Laravel\Passkeys\Contracts\PasskeyUser`, use `PasskeyAuthenticatable`, and have the `passkeys` table (`php artisan vendor:publish --tag=passkeys-migrations`).
- **Features:** switch them on or off in `gcego-sentinel.features`. A page and its routes only exist when the feature is on.

## 3. Unauthenticated redirects (optional)

Laravel's `auth` middleware sends guests to `route('login')`. To send guests inside a panel to that panel's login page instead, add this in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn (Request $request) => app(\Gcego\Sentinel\SentinelManager::class)->loginUrl($request));
})
```

## Assets

| Mode | How it works |
| --- | --- |
| `package` (default) | Sentinel serves `resources/dist/sentinel.css` (about 34 KB minified) and `sentinel.js` (about 15 KB, including the passkey ceremonies) at `/gcego-sentinel/assets/...`. URLs carry a content hash, so the files are cached for a year. No build step is needed. |
| `vite` | Your Vite build compiles the sources. Add `vendor/gcego/sentinel/resources/css/sentinel.css` and `vendor/gcego/sentinel/resources/js/sentinel.js` to the Vite inputs, and list them in `gcego-sentinel.assets.vite`. |

The stylesheet scans only Sentinel's own views and presets, so authentication pages never load dashboard CSS.

After changing presets or views inside the package, run `composer build-assets`. A test fails when `resources/dist` is stale.

## Deployment

- **Caching:** `php artisan config:cache` and `php artisan route:cache` are supported. The configuration is plain data and every route uses a controller.
- **Cookies:** use `SESSION_SECURE_COOKIE=true`, `same_site` = `lax` and `http_only` = `true` in production. The health check fails otherwise.
- **Cache:** use a shared store (redis, database, memcached) on more than one server. It holds the rate limits, one-time-code replay claims and session epochs.
- **Notifications:** security notices are queued after commit. Run a queue worker, or use the `sync` connection.
- **Browser sessions:** listing them needs the `database` session driver (`php artisan make:session-table`).

The full checklist is in `docs/sentinel/DEPLOYMENT-HARDENING.md` at the repository root.
