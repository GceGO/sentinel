<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Middleware;

use Closure;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * sentinel.registration, on the "register" and "register.store" routes: registration only proceeds when the RegistrationPolicy allows it
 * (for the panel whose register page the visitor opened, or the public
 * policy), and, when terms are configured, only once they are accepted.
 * Otherwise registration does not exist (404), so a hidden page cannot be
 * bypassed by posting to the endpoint directly.
 */
final readonly class EnsureRegistrationIsAllowed
{
    public function __construct(
        private SentinelConfig $config,
        private AuthenticationPanels $panels,
        private AuthenticationFeatureResolver $features,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // The site's own register page is the public one.
        if ($request->isMethod('GET') && $request->hasSession()) {
            $request->session()->forget([SessionKeys::Panel, SessionKeys::Tenant]);
        }

        $id = $request->hasSession() ? $request->session()->get(SessionKeys::Panel) : null;
        $panel = is_string($id) ? $this->panels->find($id) : null;

        if (! $this->features->enabled(Feature::Registration, $panel)) {
            throw new NotFoundHttpException;
        }

        if (! $request->isMethod('GET') && $this->config->termsUrl !== null && ! $request->boolean('terms')) {
            throw ValidationException::withMessages(['terms' => [__('gcego-sentinel::messages.register.terms_required')]]);
        }

        return $next($request);
    }
}
