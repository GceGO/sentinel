<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;

/**
 * APP_KEY as the key of Sentinel's HMACs (session references, password
 * fingerprints, browser tokens, recovery code hashes). Never logged.
 */
final class AppKey
{
    public static function get(?Repository $settings = null): string
    {
        $key = ($settings ?? Container::getInstance()->make(Repository::class))->get('app.key');

        return is_string($key) ? $key : '';
    }
}
