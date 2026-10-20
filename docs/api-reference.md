# API reference

## `Gcego\Sentinel\SentinelManager`

The entry point (no facade). It is stateless, and services are resolved per call.

| Method | Returns |
| --- | --- |
| `config()` | `SentinelConfig` (validated) |
| `guardName()`, `guard()`, `provider()` | The session guard Sentinel authenticates with (`gcego-sentinel.guard`) and its user provider |
| `features()`, `enabled(Feature $feature, ?AuthenticationPanel $panel = null)` | Feature availability |
| `panels()` | `AuthenticationPanels` |
| `layouts()`, `layout(?string $name = null)` | `LayoutManager`, `AuthenticationLayout` |
| `branding(?AuthenticationPanel $panel = null)` | `AuthenticationBranding` |
| `redirects()` | `AuthenticationRedirectResolver` |
| `context(Request $request, AuthenticationPage $page)` | `AuthenticationContext` |
| `view(AuthenticationPage $page)` | The page's view (override or Sentinel's) |
| `page(Request $request, AuthenticationPage $page, array $data = [])` | The rendered page, `no-store` (404 when the page is unavailable) |
| `loginUrl(Request $request)` | The login page for a request (a panel's inside panel paths) |
| `inspect()` | `Gcego\Core\Diagnostics\DiagnosticReport` |
| `health()` | `AuthenticationHealthCheck` (`run()`, `healthy()`, `summary()`) |

## Enums and values

| Type | Members |
| --- | --- |
| `AuthenticationPage` | `Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`, `ConfirmPassword`, `TwoFactorChallenge`, `AccountSecurity`, `AccountUnavailable`, `AccountRecovery`, `SessionExpired`, `LoggedOut`: `view()`, `feature()`, `isSwitchable()`, `title()` |
| `Features\Feature` | `Login`, `Registration`, `PasswordReset`, `EmailVerification`, `PasswordConfirmation`, `TwoFactorAuthentication`, `Passkeys`, `ProfileInformation`, `PasswordUpdate`, `BrowserSessions`, `AccountSecurity` |
| `Accounts\AccountState` | `Active`, `Suspended`, `Disabled`, `Review`, `Held`: `allowsAccess()` |
| `Panels\AuthenticationPanel` | `id`, `path`, `title`, `home`, `permission`, `registration`, `twoFactor`, `layout`, `theme`, `logoutRedirect`, `branding`; `isTenant()`, `loginUri()`, `registerUri()`, `home(?string $tenant)`, `match(string $path)` |
| `Branding\AuthenticationBranding` | Validated branding: `merge()`, `withName()`, `accentForeground()`, `css()`, `initials()` |
| `Context\AuthenticationContext` | See [customization.md](customization.md) |
| `Redirects\Destination` | `url`, `panel`, `tenant`, `rejection`, `twoFactorSetup`, `continue`; `isRejected()` |
| `Sessions\BrowserSession` | `id` (digest), `agent` (`UserAgent`: `browser`, `platform`, `mobile`), `ipAddress`, `lastActive`, `current` |

## Contracts

`Contracts\AuthenticationLayout`, `AuthenticationFeatureResolver`, `AuthenticationBrandingResolver`, `TenantBrandingProvider`, `AuthenticationContextResolver`, `AuthenticationRedirectResolver`, `AccountStateResolver`, `RegistrationPolicy`, `PanelAuthorizer`, `AuthenticationPanels`, `CreatesAccounts`, `UpdatesProfiles`, `SecurityAuditSink`, `RiskAssessor`. See [customization.md](customization.md) for their defaults.

## Middleware

| Alias | Class | Purpose |
| --- | --- | --- |
| `sentinel.active` | `Http\Middleware\EnsureAccountIsActive` | Ends inactive accounts' sessions |
| `sentinel.two-factor[:panel]` | `Http\Middleware\EnsureTwoFactorIsEnabled` | Requires enrollment, and a multi-factor session, where the policy says so |
| `sentinel.registration` | `Http\Middleware\EnsureRegistrationIsAllowed` | Registration policy and terms (on the register routes) |
| `sentinel.session` | `Http\Middleware\EnforceSessionSecurity` | Idle and absolute timeouts, password and epoch revocation (pushed to `web`) |
| `sentinel.step-up[:seconds]` | `Http\Middleware\RequireStepUp` | Requires a recent password confirmation |

## Routes

| Name | Default URI |
| --- | --- |
| `login`, `login.store`, `logout` | `login`, `logout` |
| `two-factor.login`, `two-factor.login.store` | `two-factor-challenge` |
| `password.request`, `password.email`, `password.reset`, `password.update` | `forgot-password`, `reset-password/{token}`, `reset-password` |
| `password.confirm`, `password.confirm.store`, `password.confirmation` | `user/confirm-password`, `user/confirmed-password-status` |
| `verification.notice`, `verification.verify`, `verification.send` | `email/verify`, `email/verify/{id}/{hash}`, `email/verification-notification` |
| `register`, `register.store` | `register` |
| `passkey.login-options`, `passkey.login`, `passkey.registration-options`, `passkey.store` | `passkeys/login/options`, `passkeys/login`, `user/passkeys/options`, `user/passkeys` |
| `sentinel.account.security` | `account/security` |
| `sentinel.account.recovery` | `account/recovery` |
| `sentinel.account.unavailable` | `account/unavailable` |
| `sentinel.session.expired` | `session-expired` |
| `sentinel.logged-out` | `signed-out` |
| `sentinel.assets` | `gcego-sentinel/assets/{file}` |
| `sentinel.panels.{id}.login` | `{panel path}/login` |
| `sentinel.panels.{id}.register` | `{panel path}/register` |

Paths are set in `gcego-sentinel.routes`. Names are in `Routing\SentinelRoutes`.

## Events

| Event | Payload |
| --- | --- |
| `Events\AccountAccessDenied` | `userKey`, `state`, `guard`, `stage` (`login`, `two-factor` or `request`) |
| `Events\LoginRejected` | `userKey`, `panel`, `tenant`, `reason` |
| `Events\PasswordChanged` | `userKey`, `guard` |
| `Events\BrowserSessionsRevoked` | `userKey`, `guard`, `count`, `all` |

Every event implements `Events\SecurityEvent` and is written to the audit trail. The others are `LoginSucceeded`, `LoginFailed`, `LogoutCompleted`, `RegistrationCompleted`, `EmailVerified`, `EmailChanged`, `PasswordResetCompleted`, `TwoFactorEnabled`, `TwoFactorDisabled`, `RecoveryCodesRegenerated`, `RecoveryCodeUsed`, `TwoFactorChallengeFailed`, `PasskeyRegistered`, `PasskeyRemoved`, `SessionExpired`, `SecurityHoldApplied`, `SecurityHoldReleased` and `SuspiciousSignIn`.

## Livewire components

`gcego-sentinel.profile-information`, `gcego-sentinel.update-password`, `gcego-sentinel.two-factor`, `gcego-sentinel.passkeys`, `gcego-sentinel.browser-sessions`

## Commands

- `gcego:sentinel-install {--dry-run} {--views} {--lang} {--migrations} {--strict}`
- `gcego:sentinel-inspect {--json}`
- `gcego:sentinel-hold {user} {--reason=}`, `gcego:sentinel-release {user} {--note=}`
- `gcego:sentinel-reset-two-factor {user} {--force}`

## Testing

`Testing\InteractsWithSentinel`: `enableTwoFactor()`, `twoFactorCode()`, `withConfirmedPassword()`
