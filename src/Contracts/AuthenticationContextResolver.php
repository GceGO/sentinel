<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\AuthenticationContext;
use Illuminate\Http\Request;

/**
 * Builds the context of one page render: page, panel, tenant, layout,
 * branding, theme and available features. Never queries for guests unless
 * a tenant panel has to find its tenant.
 */
interface AuthenticationContextResolver
{
    public function resolve(Request $request, AuthenticationPage $page): AuthenticationContext;
}
