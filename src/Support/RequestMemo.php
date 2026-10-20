<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * Remembers a value for the current HTTP request only (in the request's
 * attributes), so repeated checks in one request cost one query while a
 * new request always sees fresh data, even in long-running servers that
 * reuse services and controllers.
 */
final class RequestMemo
{
    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public static function remember(string $key, Closure $resolve): mixed
    {
        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;

        if (! $request instanceof Request) {
            return $resolve();
        }

        $key = 'gcego-sentinel.memo.'.$key;

        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $value = $resolve();
        $request->attributes->set($key, $value);

        return $value;
    }

    public static function forget(string $key): void
    {
        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;

        if ($request instanceof Request) {
            $request->attributes->remove('gcego-sentinel.memo.'.$key);
        }
    }
}
