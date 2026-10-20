<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Panels;

use Gcego\Security\Permissions\PermissionName;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Support\ConfigReader;
use Throwable;

/**
 * How one panel (administration, institution, teacher...) presents
 * authentication: its login page, branding and destinations, and which
 * gcego/security permission entering it requires.
 *
 * A path containing "{tenant}" ("institution/{tenant}") makes a tenant
 * panel: the tenant key in its URLs is untrusted until gcego/security has
 * verified the user's membership.
 */
final readonly class AuthenticationPanel
{
    public const string IdPattern = '/^[a-z][a-z0-9_-]{0,63}$/';

    public const string TenantPlaceholder = '{tenant}';

    public const string TenantKeyPattern = '[A-Za-z0-9_\-:.]{1,100}';

    public function __construct(
        public string $id,
        public string $path,
        public ?string $title = null,
        public ?string $home = null,
        public ?string $permission = null,
        public bool $registration = false,
        public bool $twoFactor = false,
        public ?string $layout = null,
        public ?string $theme = null,
        public ?string $logoutRedirect = null,
        public AuthenticationBranding $branding = new AuthenticationBranding,
    ) {
        $key = 'gcego-sentinel.panels.'.$id;

        if (preg_match(self::IdPattern, $id) !== 1) {
            throw AuthenticationConfigurationException::invalid('gcego-sentinel.panels', sprintf('"%s" is not a panel id (lowercase letters, digits, "-" and "_").', mb_substr($id, 0, 60)));
        }

        if (preg_match('/^[a-z0-9_-]+(?:\/(?:[a-z0-9_-]+|\{tenant\}))*$/', $path) !== 1 || substr_count($path, self::TenantPlaceholder) > 1) {
            throw AuthenticationConfigurationException::invalid($key.'.path', 'use lowercase URL segments such as "admin" or "institution/{tenant}" (at most one {tenant}, not first).');
        }

        foreach (['home' => $home, 'logout_redirect' => $logoutRedirect] as $name => $url) {
            if ($url !== null && (! str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $url) === 1)) {
                throw AuthenticationConfigurationException::invalid($key.'.'.$name, 'use a root-relative path such as "/admin".');
            }
        }

        if ($home !== null && $this->isTenant() && ! str_contains($home, self::TenantPlaceholder)) {
            throw AuthenticationConfigurationException::invalid($key.'.home', 'a tenant panel\'s home must contain {tenant}.');
        }

        if ($permission !== null) {
            try {
                PermissionName::assert($permission);
            } catch (Throwable $exception) {
                throw AuthenticationConfigurationException::invalid($key.'.permission', $exception->getMessage());
            }
        }

        if ($theme !== null && ! in_array($theme, ['system', 'light', 'dark'], true)) {
            throw AuthenticationConfigurationException::invalid($key.'.theme', 'use "system", "light" or "dark".');
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(string $id, array $values): self
    {
        $read = new ConfigReader($values, 'gcego-sentinel.panels.'.$id);

        return new self(
            $id,
            trim($read->string('path', $id), '/'),
            $read->nullableString('title'),
            $read->nullableString('home'),
            $read->nullableString('permission'),
            $read->bool('registration', false),
            $read->bool('two_factor', false),
            $read->nullableString('layout'),
            $read->nullableString('theme'),
            $read->nullableString('logout_redirect'),
            AuthenticationBranding::fromArray(is_array($values['branding'] ?? null) ? $values['branding'] : [], $read->key('branding')),
        );
    }

    public function isTenant(): bool
    {
        return str_contains($this->path, self::TenantPlaceholder);
    }

    /**
     * The login route's URI ("admin/login", "institution/{tenant}/login").
     */
    public function loginUri(): string
    {
        return $this->path.'/login';
    }

    public function registerUri(): string
    {
        return $this->path.'/register';
    }

    /**
     * The panel's home for a (verified) tenant key: "/admin", "/institution/42".
     */
    public function home(?string $tenant = null): string
    {
        $home = $this->home ?? '/'.$this->path;

        return $this->isTenant() ? str_replace(self::TenantPlaceholder, rawurlencode((string) $tenant), $home) : $home;
    }

    /**
     * Whether a path ("/admin/users") lies inside this panel, and for a tenant
     * panel, the (unverified) tenant key it names.
     *
     * @return array{tenant: ?string}|null
     */
    public function match(string $path): ?array
    {
        $pattern = '#^/'.str_replace(preg_quote(self::TenantPlaceholder, '#'), '([^/]{1,300})', preg_quote($this->path, '#')).'(?:/|$)#';

        if (preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        if (! $this->isTenant()) {
            return ['tenant' => null];
        }

        $tenant = rawurldecode($matches[1]);

        return preg_match('/^'.self::TenantKeyPattern.'$/', $tenant) === 1 ? ['tenant' => $tenant] : null;
    }
}
