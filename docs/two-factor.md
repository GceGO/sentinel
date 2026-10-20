# Two-factor authentication

Sentinel implements TOTP (RFC 6238: authenticator apps, 6 digits, 30-second steps) with `pragmarx/google2fa`, and QR codes with `bacon/bacon-qr-code`. The secret is stored in `users.two_factor_secret`, encrypted with APP_KEY. `features.two_factor` switches it on or off.

## Challenge

`/two-factor-challenge` exists only for a sign-in whose password was right (a `PendingLogin` in the regenerated session). The visitor stays a guest until the code is accepted.

- **Input:** the authenticator code, with `inputmode="numeric"` and `autocomplete="one-time-code"`. A link switches to a recovery code (`?recovery=1`, no JavaScript needed).
- **Window:** codes are accepted within ±`two_factor.window` steps.
- **Each code works once:** the time step is claimed atomically, and a step at or before the last accepted one is refused.
- **Limits:** after `two_factor.challenge_attempts` wrong codes, or `two_factor.challenge_timeout` seconds, the password must be entered again. The endpoint is also throttled per pending account.
- **Panels:** the panel the sign-in started from is kept, so panel access is checked after the challenge as well. Accounts suspended in the meantime are refused.

## Enrollment (account security)

1. **Turn on:** always asks for the password first, then creates a new secret. It is pending: not used at sign-in yet.
2. **Scan:** the QR code (rendered on white, so it scans in dark mode too) and the setup key are shown only while setup is pending.
3. **Confirm:** a code from the app activates two-factor authentication. This fires `Events\TwoFactorEnabled`, emails the owner, and upgrades the current session to multi-factor. The recovery codes are shown once.
4. **Abandoned setup:** a setup that was never confirmed is reset when the page is opened again in a new session.

## Recovery codes

- **Generation:** 8 codes (`two_factor.recovery_codes`) of about 100 bits each, formatted `XXXXX-XXXXX-XXXXX-XXXXX`.
- **Storage:** stored only as HMAC-SHA256 hashes in `sentinel_recovery_codes`. They can never be shown again: the page shows how many remain, and warns when 2 or fewer are left.
- **Use:** each code works once, through an atomic conditional update. Each use fires `RecoveryCodeUsed` and emails the owner.
- **Confirmation:** regenerating codes and turning two-factor off always need a recent password confirmation, checked server-side in the component action itself. That confirmation is Laravel's `auth.password_confirmed_at`, valid for `passwords.confirmation_timeout` (or `auth.password_timeout`).
- **Never stored client-side:** codes are never a Livewire property and never part of a snapshot. They appear only in the response to the action that creates them.
- **Copy and print:** copy uses the Clipboard API; print shows only the code list.
- **Never stored anywhere else:** Sentinel does not log, cache or send codes anywhere.

## Lost device

The `/account/recovery` page explains the options: a recovery code, a passkey on another device, or an administrator. After verifying the person's identity, the administrator runs `php artisan gcego:sentinel-reset-two-factor {user}`.

## Requiring it

See [panels.md](panels.md#two-factor-policy): the panel's `two_factor` flag, `two_factor.required_permissions` and the `sentinel.two-factor` middleware.

Required users:
- must sign in with a second factor;
- never get a remember-me cookie;
- are signed out from sessions that never proved one.

Passkeys count as the second factor when `passkeys.satisfies_two_factor` is on. See `docs/sentinel/MFA-AND-PASSKEYS.md`.
