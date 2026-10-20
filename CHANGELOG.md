# Changelog

## Unreleased - 2026-10-18

Sentinel is its own authentication engine.

- **Changed:** the stylesheet imports gcego/swatches' color tokens and color bridge (gcego/chroma no longer declares colors). `AuthenticationBranding::css()` compiles the accent with gcego/swatches into accessible light and dark tokens: an accent too light for white surfaces is darkened, and dark mode gets its own values. Contrast helpers come from `Gcego\Swatches\Accessibility\Contrast`.

- **Added:** native sign-in, logout, two-factor challenge, password reset, email verification, password confirmation, registration and passkey endpoints.
- **Added:** TOTP with replay protection, recovery codes stored as hashes, passkeys (through laravel/passkeys), session timeouts and revocation, step-up confirmation (`sentinel.step-up`), security holds, an audit trail, security notifications and risk signals.
- **Added:** `gcego:sentinel-hold`, `gcego:sentinel-release` and `gcego:sentinel-reset-two-factor`, plus an additive migration with four `sentinel_*` tables.
- **Changed:** configuration is now `features`, `passwords`, `two_factor`, `passkeys`, `sessions`, `rate_limits`, `audit` and `notifications`.
- See `docs/sentinel/` at the repository root.

## 0.1.0 - 2026-10-17

First release.

- **Pages:** login, register, forgot and reset password, verify email, confirm password and two-factor challenge, with panel-aware responses.
- **Panels:** login and register pages per panel, and tenant panels (`/institution/{tenant}/login`) with membership and tenant permissions verified through gcego/security.
- **Account security center:** profile, password, two-factor enrollment and recovery codes, and browser sessions (Livewire), with server-side password confirmation and no secrets in snapshots.
- **Hardening:** account states (suspended, disabled) enforced at login, after two-factor and on every request; enumeration-safe password reset requests; extra rate limits; friendly lockout and session-expired pages; safe redirects.
- **Design:** four layouts (centered, split, minimal, branded), light, dark and system themes on gcego/chroma tokens, validated branding with tenant branding, Blade components, and a prebuilt Tailwind CSS v4 stylesheet and script (CSP-friendly).
- **Localization:** English and French.
- **Tooling:** `gcego:sentinel-install` (idempotent, dry run), `gcego:sentinel-inspect` and the `AuthenticationHealthCheck`; a Testbench workbench demo.
