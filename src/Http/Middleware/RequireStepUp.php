<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Middleware;

use Closure;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\Security\StepUp;
use Gcego\Sentinel\Support\Routes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

/**
 * sentinel.step-up[:seconds]: the route needs a password confirmed within
 * $seconds (default gcego-sentinel.passwords.confirmation_timeout, else
 * auth.password_timeout). Put it on high-risk operations (role changes,
 * exports, security settings):
 *
 *     Route::post('/admin/users/{user}/role', ...)->middleware(['auth', 'sentinel.step-up:300']);
 */
final readonly class RequireStepUp
{
    public function __construct(private StepUp $stepUp, private UrlGenerator $urls) {}

    public function handle(Request $request, Closure $next, ?string $seconds = null): mixed
    {
        $window = is_numeric($seconds) && (int) $seconds > 0 ? (int) $seconds : null;

        if ($this->stepUp->confirmed($request->session(), $window)) {
            return $next($request);
        }

        if ($request->expectsJson() || ! Routes::has(SentinelRoutes::PasswordConfirm)) {
            return new JsonResponse(['message' => __('gcego-sentinel::messages.confirm.required')], 423);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->getRequestUri());
        }

        return new RedirectResponse($this->urls->route(SentinelRoutes::PasswordConfirm));
    }
}
