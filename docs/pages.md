# Pages, redirects and account states

| Page | Route | Middleware | Form posts to |
| --- | --- | --- | --- |
| Login | `login` | guest | `login.store` (throttled), or `passkey.login` |
| Panel login | `sentinel.panels.{id}.login` | web, guest | `login.store` |
| Registration | `register`, `sentinel.panels.{id}.register` | guest, `sentinel.registration` | `register.store` |
| Forgot password | `password.request` | guest | `password.email` |
| Reset password | `password.reset` | guest (sent with `Referrer-Policy: no-referrer`) | `password.update` |
| Verify email | `verification.notice` | auth | `verification.send`; the link is `verification.verify` (signed) |
| Confirm password | `password.confirm` | auth | `password.confirm.store` |
| Two-factor challenge | `two-factor.login` | guest, with a pending sign-in | `two-factor.login.store` |
| Account security | `sentinel.account.security` | web, auth, `sentinel.active` | Livewire, and the passkey endpoints |
| Account recovery | `sentinel.account.recovery` | web | none |
| Account unavailable | `sentinel.account.unavailable` | web | none |
| Session expired | `sentinel.session.expired` | web | none |
| Signed out | `sentinel.logged-out` | web | none |

Every page is sent with `Cache-Control: no-store, private`.

## Page details

- **Login:**
  - email (or username) and password, with a show/hide toggle;
  - remember me (not offered when `features.remember` is off);
  - a forgot-password link and an optional registration link;
  - "Sign in with a passkey" when passkeys are on.

  Errors are shown in an announced summary and next to each field. Wrong passwords and unknown accounts get the same message.
- **Forgot password:** known, unknown and recently-sent addresses all get the same message, so the form cannot be used to find accounts.
- **Reset password:** shows the password rules from Sentinel's `PasswordPolicy`, with a strength hint. Expired, used or unknown links all get the same "invalid link" message.
- **Two-factor challenge:** an authenticator code (`autocomplete="one-time-code"`) or, at `?recovery=1`, a recovery code. Neither needs JavaScript. A link leads to the account recovery page.

## Destinations (`AuthenticationRedirectResolver`)

| After | Goes to |
| --- | --- |
| Login | The safe intended URL; else the panel's home when signing in on a panel page; else `gcego-sentinel.home`. If the intended URL or the panel is one the user may not enter, the sign-in is refused (no session is created) and they are back on that panel's login page with a message. If two-factor is required but missing, the account security page, with a "Continue" link afterwards. |
| Registration | The verification notice when the email must be verified; the panel's home only if the new account may enter it; otherwise `gcego-sentinel.home` |
| Verification | The safe intended URL (only into a panel the user may enter), the panel's home, or `gcego-sentinel.home`, with `?verified=1` |
| Password reset | The login page the reset started from (panel or site) |
| Logout | The panel's `logout_redirect`, the panel's login page, `gcego-sentinel.logout_redirect`, or the signed-out page. The form's `panel` field only picks a configured panel. |

`SafeRedirectValidator` accepts only:

- root-relative paths;
- http(s) URLs on the request's host or `APP_URL`'s, returned as relative URLs.

It rejects protocol-relative URLs (`//`), backslashes, encoded slashes, control characters, other schemes, user information, and authentication pages (loops).

## Account states

Set `gcego-sentinel.account_states.attribute` (for example `status`) and the values that mean suspended, disabled or under review. Enums are supported. Or bind your own `Contracts\AccountStateResolver`. A Sentinel security hold makes any account `held`.

An inactive account is refused at three points, always server-side:

1. **When the sign-in proves its credentials** (password or passkey): a generic "cannot sign in" message, before any session exists.
2. **When the sign-in completes**, including after a two-factor challenge.
3. **On every request through `sentinel.active`**, including Livewire updates of the security components. Remember-me sessions and accounts suspended mid-session are signed out.

Each refusal fires `Events\AccountAccessDenied` (user key, state, stage).

## Lockout and expired sessions

- **Lockout:** a 429 from a rate limiter on an authentication endpoint returns to the form with "Too many attempts. Please try again in N seconds".
- **Expired session:** a 419 (expired session or CSRF token) shows the session-expired page. CSRF protection itself is unchanged; the request was already rejected.
- **Ended session:** an idle or absolute timeout, a password change elsewhere, or "sign out everywhere" ends the session on its next request and shows the session-expired page.
- **JSON clients** keep the status codes.
- **Switching off:** `gcego-sentinel.errors.*`.
