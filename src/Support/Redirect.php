<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Store;

/**
 * A redirect bound to the request's session, so ->with() and ->withErrors() flash into it.
 */
final class Redirect
{
    public static function to(string $url, Request $request): RedirectResponse
    {
        $response = new RedirectResponse($url);

        $session = $request->hasSession() ? $request->session() : null;

        if ($session instanceof Store) {
            $response->setSession($session);
        }

        return $response;
    }
}
