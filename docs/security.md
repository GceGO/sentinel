# Security model

| Concern | How Sentinel handles it |
| --- | --- |
| Credentials | Checked by the guard's user provider with Laravel's hashing (a decoy hash for unknown accounts). Sentinel never stores, logs or caches passwords, and never puts them in Livewire state. |
| Enumeration | Login errors are generic and identical for unknown accounts and wrong passwords. Account-state messages are only shown after a correct password. Password reset requests get the same answer for every address. |
| Rate limiting | Laravel's `RateLimiter` only: layered sign-in limits (account+address, address, account), the two-factor challenge, password reset requests and resets, password confirmation (HTTP and Livewire), registration, passkeys and signing out other devices. Lockouts give the wait time, never whether the account exists. |
| Sessions | Sentinel regenerates the session on login (and before the two-factor challenge) and invalidates it on logout. Idle and absolute timeouts, password changes and "sign out everywhere" end sessions on any driver. Remember-me is Laravel's recaller. The health check fails on non-HTTP-only cookies and on insecure cookies in production. |
| CSRF | Laravel's middleware, unchanged. Every form has `@csrf`. A 419 on an authentication route shows the session-expired page (tested with the protection active). |
| Redirects | `SafeRedirectValidator`: internal only, no other schemes, no loops. A panel or tenant destination is only used when gcego/security allows it. |
| Panels and tenants | Signing in never implies access (see [panels.md](panels.md)). Tenant keys from URLs and sessions are untrusted until a membership is verified. |
| Account states | Enforced server-side at login, in the login responses and on every request (`sentinel.active`). |
| Two-factor | TOTP with replay protection; recovery codes stored as hashes and redeemed atomically; password confirmation enforced in the server action. Secrets and recovery codes are never in snapshots, logs, caches or events. |
| Registration | Off by default and never on for panels unless configured. Enforced on the endpoint, not only the page. No roles are granted. |
| Output | Every value is escaped. Branding is validated data (URLs, hex colors), never HTML. The QR SVG is generated server-side by bacon/bacon-qr-code from the pending secret. |
| Headers | Pages send `Cache-Control: no-store, private` and `Referrer-Policy: same-origin`, or `no-referrer` on the reset page, whose URL holds the token. Assets are external files (no inline scripts), and the brand `<style>` uses Vite's CSP nonce when set. The application's own security headers are kept. |
| Events | `AccountAccessDenied`, `LoginRejected`, `PasswordChanged` and `BrowserSessionsRevoked` carry user keys, states and counts, never credentials, tokens, codes or session ids. Laravel's `Attempting`/`Failed`/`Validated`/`Login`/`PasswordReset`/`Verified` events still fire. Every Sentinel event goes to the audit trail. |

## Known limits

- **Response timing:** requesting a reset link for an existing account sends mail. Queue notifications so response times do not differ.
- **Tenant login pages:** they reveal whether a tenant key exists (by design; see [panels.md](panels.md#tenant-login-pages)).
