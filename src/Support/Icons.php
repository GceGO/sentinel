<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Gcego\Luminaries\Luminaries;

/**
 * Sentinel's icon names, mapped to gcego/luminaries semantic aliases so an
 * application's alias overrides also apply to authentication screens.
 * <x-gcego-sentinel::icon name="lock" /> renders "authentication.locked".
 * Any other Luminaries identifier is accepted as is; unknown names fall back
 * to the info icon. Icons are decorative (aria-hidden).
 */
final class Icons
{
    public const array Map = [
        'lock' => 'authentication.locked',
        'mail' => 'authentication.email',
        'shield' => 'authentication.security',
        'key' => 'authentication.password',
        'check' => 'status.success',
        'alert' => 'forms.error',
        'info' => 'status.info',
        'warning' => 'status.warning',
        'arrow-left' => 'navigation.back',
        'arrow-right' => 'navigation.forward',
        'eye' => 'authentication.show-password',
        'eye-off' => 'authentication.hide-password',
        'desktop' => 'authentication.session',
        'mobile' => 'heroicon:device-phone-mobile',
        'sun' => 'settings.light',
        'moon' => 'settings.dark',
        'monitor' => 'settings.system',
        'clock' => 'status.pending',
        'logout' => 'authentication.logout',
        'copy' => 'actions.copy',
        'printer' => 'actions.print',
        'user' => 'authentication.user',
        'blocked' => 'heroicon:no-symbol',
        'refresh' => 'actions.refresh',
    ];

    /**
     * The Luminaries identifier rendered for a Sentinel icon name.
     */
    public static function name(string $name): string
    {
        if (isset(self::Map[$name])) {
            return self::Map[$name];
        }

        return app(Luminaries::class)->exists($name) ? $name : self::Map['info'];
    }
}
