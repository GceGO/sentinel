<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http;

use Gcego\Sentinel\Redirects\SafeRedirectValidator;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\Redirect;
use Gcego\Sentinel\Support\Routes;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Friendly handling of two errors on authentication routes only (other
 * routes keep the application's handling, JSON clients keep the status):
 *
 *  - 419 (expired session / CSRF token): the session-expired page, which
 *    explains it and links back to sign in. CSRF protection is unchanged:
 *    the request was rejected before this runs.
 *  - 429 (rate limited): back to the form with a lockout message that
 *    gives the wait time but never says whether the account exists.
 */
final readonly class AuthenticationErrors
{
    /**
     * Throttled route => the field that shows the lockout message.
     */
    private const array LockoutFields = [
        'login.store' => null,
        'two-factor.login.store' => 'code',
        'password.email' => 'email',
        'password.update' => 'email',
        'register.store' => 'email',
        'password.confirm.store' => 'password',
        'verification.send' => 'verification',
    ];

    public function __construct(private SentinelConfig $config, private UrlGenerator $urls, private SafeRedirectValidator $redirects) {}

    public function register(ExceptionHandler $handler): void
    {
        if (! $handler instanceof Handler || ! $this->config->enabled) {
            return;
        }

        $handler->renderable(fn (HttpExceptionInterface $exception, Request $request): ?RedirectResponse => $this->render($exception, $request));
    }

    public function render(HttpExceptionInterface $exception, Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        $route = $request->route();
        $name = $route instanceof Route ? $route->getName() : null;

        if ($name === null || (! in_array($name, SafeRedirectValidator::AuthenticationRoutes, true) && ! str_starts_with($name, 'sentinel.'))) {
            return null;
        }

        if ($exception->getStatusCode() === 419 && $this->config->sessionExpiredPage && Routes::has(SentinelRoutes::SessionExpired)) {
            return new RedirectResponse($this->urls->route(SentinelRoutes::SessionExpired));
        }

        if ($exception->getStatusCode() === 429 && $this->config->lockoutPage && array_key_exists($name, self::LockoutFields)) {
            $retry = $exception->getHeaders()['Retry-After'] ?? 60;
            $seconds = max(1, is_numeric($retry) ? (int) $retry : 60);
            $field = self::LockoutFields[$name] ?? $this->config->identifier;
            $back = $this->redirects->local($request->headers->get('referer'), $request) ?? $this->urls->route('login');

            return Redirect::to($back, $request)
                ->withErrors([$field => __('gcego-sentinel::messages.lockout', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)])])
                ->withInput($request->only($this->config->identifier, 'email', 'remember'));
        }

        return null;
    }
}
