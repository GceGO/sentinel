# Panels and tenants

A panel is an area such as administration, an institution or teachers. In Sentinel it has its own login page, branding and destinations. Panels come from `gcego-sentinel.panels`, or from a `Contracts\AuthenticationPanels` binding (for gcego/panels).

```php
'panels' => [
    'admin' => [
        'path' => 'admin',                 // login at /admin/login, home /admin
        'title' => 'Administration',
        'permission' => 'panels.admin',    // gcego/security permission; null = any signed-in user
        'two_factor' => true,              // members must enroll before entering
        'registration' => false,           // the default; never enable it for administration
        'layout' => 'split', 'theme' => null, 'home' => null, 'logout_redirect' => null,
        'branding' => ['accent' => '#1e3a8a'],
    ],
    'institution' => ['path' => 'institution/{tenant}', 'title' => 'Institution', 'permission' => 'institution.access'],
],
```

## Signing in to a panel

1. The panel's login page remembers the panel (and, for tenant panels, the tenant key) in the session. The form posts to `login.store`.
2. Sentinel checks the credentials, then the second factor when the user has one (the same applies to a passkey sign-in).
3. The login pipeline resolves the panel: from the intended URL's path, or from the login page.
4. `PanelAuthorizer` checks access through gcego/security, for that user, fresh:
   - a global `Permission` for ordinary panels;
   - for tenant panels, `TenantAuthorizer` first verifies the membership (unknown tenants and non-members are denied alike), then `Permission::inTenant()` checks the permission inside that tenant. Global roles and `Gate::before` rules never open a tenant panel.
5. If the user may not enter, no session is created and they are returned to the panel's login page with "This account cannot sign in to {panel}". `Events\LoginRejected` is fired.
6. Otherwise they go to the intended URL inside the panel, or the panel's home. For a tenant panel, the tenant becomes the session's tenant (`SecurityManager::switchTenant()`, which `ResolveTenant` verifies again on every request).

Signing in never grants a panel, a tenant or a membership. An intended URL in another tenant is checked against that tenant, so redirects never cross tenant boundaries.

## Tenant login pages

`/institution/{tenant}/login` shows the institution's name, and its `TenantBrandingProvider` branding when one is bound. An unknown tenant key is a 404.

This does reveal whether a tenant key exists. That is the purpose of a branded, public tenant login page. Leave `{tenant}` out of the path if that is not acceptable.

## Two-factor policy

Two-factor authentication is required for:

- members of panels with `two_factor` set to `true`;
- holders of any permission in `gcego-sentinel.two_factor.required_permissions` (checked through gcego/security).

It is enforced server-side in three places:

1. **Login:** the login pipeline sends such users to the account security page instead of the panel. They never get a remember-me cookie.
2. **Panel routes:** the `sentinel.two-factor[:panel]` middleware blocks them until they enroll. It also signs out a session that never proved the second factor. gcego/studio adds it to every panel.
3. **Account security:** they cannot turn two-factor authentication off.

```php
Route::middleware(['auth', 'sentinel.active', 'sentinel.two-factor:admin'])->prefix('admin')->group(/* ... */);
```

Users whose policy requires two-factor authentication cannot disable it.

## Route collisions

Panel login and register routes are registered after the application's own routes. They are skipped when the name or the GET URI is already taken, so an existing `/admin/login` keeps working. Skipped routes are reported by `gcego:sentinel-inspect` (`sentinel.routes`).

## Multiple guards

Sentinel authenticates one session guard: `gcego-sentinel.guard`. The health check fails when it is not a session guard.

The supported setup is one session guard for everyone, with panels separated by authorization, as above. Applications that really need separate guards (separate user tables) must run a separate authentication stack for the other guard.
