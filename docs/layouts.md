# Layouts, branding and design

## Layouts

`gcego-sentinel.appearance.layout`, or a panel's `layout`:

| Layout | Design |
| --- | --- |
| `centered` | A card on a soft background glow |
| `split` (default) | The form beside a brand panel (name, tagline, illustration, CSS-only decoration); the brand panel hides below `lg` |
| `minimal` | The form alone, smallest footprint |
| `branded` | A card over the brand color or `branding.background` image, with an overlay |

Layouts only change presentation. Each one renders the same page body, so the forms, endpoints and security behavior are identical.

### Custom layouts

Add a custom layout in configuration (`'layouts' => ['school' => 'auth.layouts.school']`), or at runtime:

```php
app(\Gcego\Sentinel\Layouts\LayoutManager::class)->register(new \Gcego\Sentinel\Layouts\ViewLayout('school', 'auth.layouts.school'));
```

The view receives `$context` (an `AuthenticationContext`) and `$slot`. Wrap it in `<x-gcego-sentinel::document :context="$context">` and give the page body `<main id="sentinel-main">`.

## Branding

`gcego-sentinel.branding`, then a panel's `branding`, then the tenant's. Bind `Contracts\TenantBrandingProvider` to supply an institution's logo or colors on its tenant login page.

| Key | Rules |
| --- | --- |
| `logo`, `logo_dark`, `favicon`, `background`, `illustration` | Root-relative path or https URL |
| `accent` | Hex color. It sets `--gcego-primary`; the text color on it is chosen for contrast. |
| `name`, `tagline`, `footer` | Plain text, always escaped |
| `legal` (label => URL), `support_url`, `support_email` | Relative, http(s) or `mailto:` |

Invalid values throw `AuthenticationConfigurationException` at boot. Branding never accepts HTML.

## Themes

- **Modes:** `system` (default), `light` or `dark`, from `appearance.theme` or a panel's `theme`.
- **Colors:** gcego/chroma's tokens, the same as Admin Studio, so a gcego/chroma theme or a brand accent restyles every page.
- **Switch:** with styling's switching enabled, the footer shows a light/dark/system switch that shares styling's stored preference.
- **No flash:** `sentinel.js` applies the mode in `<head>`, before first paint.

## Components

These are anonymous Blade components, used as `<x-gcego-sentinel::name>`:

| Group | Components |
| --- | --- |
| Page structure | `document`, `page`, `brand`, `header`, `footer`, `appearance` |
| Forms | `input`, `password` (show/hide, `guidance` for rules and strength), `checkbox`, `button` (submit loading state, `loading` for Livewire targets) |
| Feedback | `errors` (summary), `field-error`, `status` (`role=status`, or `role=alert` for danger) |
| Navigation | `divider`, `back-link` |
| Status | `badge`, `account-status` |
| Two-factor | `recovery-codes` (copy and print), `qr-code` |
| Icons | `icon` |

Fields support:

- label, help text and a required marker;
- errors tied to the field with `aria-describedby` and `aria-invalid`;
- autocomplete tokens (`username`, `current-password`, `new-password`, `one-time-code`...);
- disabled and loading states.

## Tailwind CSS v4 presets

Classes live in `Presets\SentinelPresets` and are registered as gcego/chroma presets named `sentinel.<name>`. To override one:

```php
app(\Gcego\Chroma\ChromaManager::class)->definePreset('sentinel.card', 'w-full rounded-none border-0 bg-gcego-surface p-8', override: true);
```

Classes that are not in Sentinel's stylesheet need your own build (`assets.mode` = `vite`).

## Responsive and motion

- **Responsive:** layouts are mobile-first, with no horizontal overflow at 320 px. The split layout collapses to the form alone, and the account center's grids stack.
- **Motion:** transitions are short and purposeful (focus rings, button press, strength bar), and all of them are disabled under `prefers-reduced-motion`.
- **Without JavaScript:** everything works. JavaScript-only controls (password toggle, copy, print, theme switch) start `hidden`.

## Accessibility (WCAG 2.2 AA target)

- **Page structure:** `lang`, a skip link, a single `h1`, and a labelled `<main>`.
- **Forms:** a label for every input; errors in an announced, focusable summary and next to each field; a polite live region for copy and theme messages.
- **Contrast:** visible focus outlines; colors are gcego/chroma tokens checked for contrast; brand accents get a contrasting text color.
- **Tests:** a test parses every page and checks these rules, including that every ARIA reference points at an element that exists.
