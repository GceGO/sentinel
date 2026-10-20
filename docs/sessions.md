# Account security center and browser sessions

`/account/security` (route `sentinel.account.security`, middleware `web, auth, sentinel.active`) shows the signed-in user's own:

- name and email;
- email verification, with a resend button;
- two-factor status;
- account state;
- security tips.

It also hosts four Livewire components, each shown only when its feature is available:

| Component | Feature | Behavior |
| --- | --- | --- |
| `gcego-sentinel.profile-information` | `updateProfileInformation` | The fields in `gcego-sentinel.profile.fields`, saved through `UpdatesUserProfileInformation`. Changing the email needs a password confirmation first, and the action re-verifies it. |
| `gcego-sentinel.update-password` | `updatePasswords` | Saved through `UpdatesUserPasswords`, which checks the current password. Keeps `AuthenticateSession` valid, fires `Events\PasswordChanged`, and optionally sends `PasswordChangedNotification`. |
| `gcego-sentinel.two-factor` | `twoFactorAuthentication` | See [two-factor.md](two-factor.md) |
| `gcego-sentinel.browser-sessions` | `sessions.enabled` | Lists sessions with the `database` driver and signs out others |

## Component security

- **Whose account:** always the guard's current user, re-read on every request. It is never component state.
- **Account state:** inactive accounts are refused (`InteractsWithAccount`, plus `sentinel.active` as Livewire persistent middleware).
- **Passwords and codes:** typed passwords and codes are cleared before every response, so they never appear in a snapshot. Fields are `wire:model` (deferred), so the value only travels with the action that uses it.
- **Locked state:** confirmation state is `#[Locked]`. Only allow-listed actions can continue after a confirmation, and every sensitive action checks the confirmation itself.
- **Rate limits:** inline password confirmation and "sign out other devices" allow 5 attempts per minute per user.

## Browser sessions

Laravel keeps a per-user session inventory only with the `database` session driver (`sessions.user_id`). Sentinel lists those rows (browser and platform from the User-Agent, IP address, last activity) and never invents sessions.

| Driver | What you get |
| --- | --- |
| `database` | The list, revoking one session (after a password confirmation), and signing out all others |
| Any other | A note that devices cannot be listed, and signing out all others |

- **Session ids stay on the server:** the browser only sees a SHA-256 digest of each id, because a real session id is a credential.
- **Sign out other devices:** asks for the password, then calls Laravel's `logoutOtherDevices()`. That re-hashes the password, which ends sessions guarded by `AuthenticateSession` and other remember-me cookies. It also deletes the user's other stored sessions and keeps the current one. `Events\BrowserSessionsRevoked` carries the count only.
- **Revoking one session:** only the user's own sessions can be revoked, and never the current one.
