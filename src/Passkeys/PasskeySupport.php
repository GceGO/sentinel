<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Passkeys;

use Illuminate\Contracts\Config\Repository;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

/**
 * Whether passkeys can work at all (stateless, safe at boot): laravel/passkeys
 * is installed and the guard's user model implements PasskeyUser.
 */
final class PasskeySupport
{
    public static function supported(Repository $settings, string $guard): bool
    {
        $model = self::userModel($settings, $guard);

        return class_exists(Passkeys::class) && $model !== null && is_a($model, PasskeyUser::class, true);
    }

    /**
     * @return class-string|null
     */
    public static function userModel(Repository $settings, string $guard): ?string
    {
        $provider = $settings->get('auth.guards.'.$guard.'.provider');
        $model = is_string($provider) ? $settings->get('auth.providers.'.$provider.'.model') : null;

        return is_string($model) && class_exists($model) ? $model : null;
    }
}
