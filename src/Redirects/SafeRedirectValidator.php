<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Redirects;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Validates redirect targets (intended URLs, "redirect" inputs) so
 * authentication can never become an open redirect or a loop:
 *
 *  - only this application: a root-relative path, or an http(s) URL whose
 *    host is the request's or APP_URL's (no user info, no other port);
 *  - never protocol-relative ("//evil.test"), backslashes, control
 *    characters or other schemes (javascript:, data:...);
 *  - never an authentication page or endpoint (login, logout, two-factor
 *    challenge, password reset...), which would loop or sign out again.
 *
 * Accepted targets are returned as a root-relative URL ("/admin?tab=1"),
 * so the host is never taken from the input.
 */
final readonly class SafeRedirectValidator
{
    /**
     * Route names that are never a destination.
     */
    public const array AuthenticationRoutes = [
        'login', 'login.store', 'logout', 'register', 'register.store',
        'password.request', 'password.reset', 'password.email', 'password.update',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        'two-factor.login', 'two-factor.login.store',
        'verification.notice', 'verification.verify', 'verification.send',
        'passkey.login-options', 'passkey.login', 'passkey.registration-options', 'passkey.store',
        'sentinel.account.unavailable', 'sentinel.account.recovery', 'sentinel.session.expired', 'sentinel.logged-out',
    ];

    public function __construct(private Router $router, private Repository $config) {}

    public function internal(mixed $url, Request $request): ?string
    {
        $relative = $this->local($url, $request);

        return $relative === null || $this->isAuthenticationRoute(self::path($relative)) ? null : $relative;
    }

    /**
     * A URL of this application as a root-relative URL, authentication pages
     * included (for returning to the form a request came from).
     */
    public function local(mixed $url, Request $request): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F\\\\\s]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
                return null;
            }

            $relative = $url;
        } else {
            $relative = $this->relativeFromAbsolute($url, $request);

            if ($relative === null) {
                return null;
            }
        }

        $path = parse_url($relative, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || str_contains(rawurldecode($path), '//')) {
            return null;
        }

        return $relative;
    }

    /**
     * The path of an accepted target ("/admin?tab=1" -> "/admin").
     */
    public static function path(string $relative): string
    {
        $path = parse_url($relative, PHP_URL_PATH);

        return is_string($path) ? $path : '/';
    }

    public function isAuthenticationRoute(string $path): bool
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            // Not a GET route of this application: no page to land on.
            return ! $this->isUnknownPath($path);
        }

        $name = $route->getName();

        return $name !== null && (in_array($name, self::AuthenticationRoutes, true) || str_starts_with($name, 'sentinel.panels.'));
    }

    private function relativeFromAbsolute(string $url, Request $request): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $allowed = [[strtolower($request->getHost()), $request->getPort()]];
        $appUrl = $this->config->get('app.url');

        if (is_string($appUrl) && is_string($appHost = parse_url($appUrl, PHP_URL_HOST))) {
            $appPort = parse_url($appUrl, PHP_URL_PORT);
            $allowed[] = [strtolower($appHost), is_int($appPort) ? $appPort : null];
        }

        foreach ($allowed as [$allowedHost, $allowedPort]) {
            if ($host === $allowedHost && ($port === null || $allowedPort === null || (int) $port === (int) $allowedPort || in_array((int) $port, [80, 443], true))) {
                return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
            }
        }

        return null;
    }

    /**
     * Unknown paths are allowed (a fallback route or a 404 page is not a loop);
     * known paths without a GET route (POST /logout) are not.
     */
    private function isUnknownPath(string $path): bool
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                $this->router->getRoutes()->match(Request::create($path, $method));

                return false;
            } catch (Throwable) {
                continue;
            }
        }

        return true;
    }
}
