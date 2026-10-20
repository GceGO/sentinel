<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;

/**
 * Whether the application registers a named route (read at call time, so
 * routes registered after Sentinel are seen).
 */
final class Routes
{
    public static function has(string $name): bool
    {
        return Container::getInstance()->make(Router::class)->getRoutes()->hasNamedRoute($name);
    }
}
