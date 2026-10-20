# Customization, localization, testing and troubleshooting

## Replace a page's view

```php
'views' => ['login' => 'auth.login'],   // receives $context (and $token/$email on reset, $recovery on the challenge)
```

The view must keep posting to Sentinel's endpoints (`$context->url('login.store')`). `php artisan gcego:sentinel-install --views` copies Sentinel's views to `resources/views/vendor/gcego-sentinel` for editing; Laravel prefers that copy.

Useful `AuthenticationContext` members:

| Member | Returns |
| --- | --- |
| `has(Feature::Registration)` | Whether the feature is available |
| `url($route)` | The route's URL, or null when it is not registered |
| `loginUrl()`, `registerUrl()`, `forgotPasswordUrl()` | Links that are null when unavailable |
| `panel`, `panelTitle()`, `tenant` | The panel and tenant, for presentation |
| `branding`, `layout`, `theme` | Presentation settings |
| `logoutFields()` | Hidden fields for a logout form |

## Other extension points

| Contract | Default | Purpose |
| --- | --- | --- |
| `AuthenticationPanels` | `ConfiguredPanels` | Where panels come from (gcego/panels) |
| `PanelAuthorizer` | `SecurityPanelAuthorizer` | Panel and tenant access |
| `RegistrationPolicy` | `ConfiguredRegistrationPolicy` | Who may register where |
| `AccountStateResolver` | `AttributeAccountStateResolver` | Suspended and disabled accounts |
| `AuthenticationBrandingResolver` | `ConfiguredBrandingResolver` | Branding layers |
| `TenantBrandingProvider` | none | Institution branding |
| `AuthenticationRedirectResolver` | `SentinelRedirectResolver` | Destinations |
| `AuthenticationContextResolver` | `SentinelContextResolver` | Page context |
| `AuthenticationFeatureResolver` | `ConfiguredFeatureResolver` | Feature availability |
| `CreatesAccounts` | `DefaultAccountCreator` | Creating registered accounts |
| `UpdatesProfiles` | `DefaultProfileUpdater` | Saving profile fields |
| `SecurityAuditSink` | `DatabaseAuditSink` | Where audit entries go |
| `RiskAssessor` | `ConservativeRiskAssessor` | Sign-in risk signals |
| `AuthenticationLayout` | `ViewLayout` | Layouts |

## Localization

- **Shipped:** English and French (`resources/lang/{en,fr}/messages.php`, namespace `gcego-sentinel`). Every string in the views is translated.
- **Overriding:** publish with `--lang` and edit `lang/vendor/gcego-sentinel/{locale}/messages.php`.
- **Choosing the locale:** Sentinel uses `app()->getLocale()`; set it in your own middleware.
- **Laravel's own messages:** password broker statuses (`passwords.*`) and validation messages come from the application's `lang/` files. Add French ones (for example from laravel-lang) for a fully French flow.

## Testing your application

```php
uses(\Gcego\Sentinel\Testing\InteractsWithSentinel::class);

it('signs in with two-factor authentication', function () {
    $user = User::factory()->create();
    $this->enableTwoFactor($user);                         // Sentinel's setup, confirmed with a real code

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($user)])->assertRedirect('/dashboard');
});

$this->withConfirmedPassword()->get('/account/security');  // as if the password was just confirmed
```

Sentinel's Livewire components clear secrets before every response. In Livewire tests, send the values and the action in one request, as a browser does:

```php
$component->update(calls: [['method' => 'updatePassword', 'params' => [], 'path' => '']], updates: ['state.current_password' => '...', 'state.password' => '...', 'state.password_confirmation' => '...']);
```

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| `/login` is a 404 or shows a different page | `pages.login` must be on, and no other route may use the `login` name or URI (Sentinel then skips its own). Run `gcego:sentinel-inspect`. |
| Unstyled pages | In `package` mode the asset route must be reachable (`gcego-sentinel/assets/...`); in `vite` mode the entries must be in your Vite build. |
| `/admin/login` is not Sentinel's | The URL or name was already taken; see the `sentinel.routes` check. |
| Registration is a 404 | `pages.register` and the registration policy must both allow it. |
| No "Browser sessions" list | Use the `database` session driver. |
| Rate limiter errors | Keep `gcego-sentinel.rate_limits.enabled` = `true`; the named limiters are registered at boot. |
| Signed out after every request | Use a persistent, shared cache store; session epochs and replay claims live there. |
