<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\SentinelContextResolver;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A panel's own login and register pages (/admin/login,
 * /institution/{tenant}/login). They render Sentinel's pages with the
 * panel's branding and remember the panel in the session, so the shared
 * endpoints (login, two-factor challenge, register) know where the
 * sign-in started.
 */
final readonly class PanelPageController
{
    public function __construct(private SentinelManager $sentinel, private SentinelContextResolver $contexts) {}

    public function login(Request $request): Response
    {
        return $this->show($request, AuthenticationPage::Login);
    }

    public function register(Request $request): Response
    {
        return $this->show($request, AuthenticationPage::Register);
    }

    private function show(Request $request, AuthenticationPage $page): Response
    {
        [$panel, $tenant] = $this->contexts->panel($request);

        if ($panel === null || ($panel->isTenant() && ($tenant === null || $this->contexts->tenant($tenant) === null))) {
            throw new NotFoundHttpException;
        }

        $request->session()->put(SessionKeys::Panel, $panel->id);
        $panel->isTenant() ? $request->session()->put(SessionKeys::Tenant, $tenant) : $request->session()->forget(SessionKeys::Tenant);

        return $this->sentinel->page($request, $page);
    }
}
