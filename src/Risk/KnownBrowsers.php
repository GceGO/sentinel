<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Risk;

use Gcego\Sentinel\Sessions\UserAgent;
use Gcego\Sentinel\Support\AppKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Has this account signed in from this browser before?" through a random,
 * HttpOnly cookie (encrypted by the web middleware) whose keyed hash is
 * stored per account. User agents are only a label, never the identity.
 */
final readonly class KnownBrowsers
{
    public const string Cookie = 'gcego_sentinel_browser';

    public function __construct(private Repository $settings) {}

    public function recognizes(Request $request, Authenticatable $user): bool
    {
        $token = $request->cookies->get(self::Cookie);

        if (! is_string($token) || strlen($token) !== 40) {
            return false;
        }

        try {
            return KnownBrowser::query()->where('user_id', $user->getAuthIdentifier())->where('token_hash', $this->hash($token))->exists();
        } catch (Throwable) {
            return true;
        }
    }

    public function remember(Request $request, Authenticatable $user): void
    {
        $token = $request->cookies->get(self::Cookie);
        $token = is_string($token) && strlen($token) === 40 ? $token : Str::random(40);
        $agent = UserAgent::parse($request->userAgent());

        try {
            $browser = KnownBrowser::query()->firstOrNew(['user_id' => $user->getAuthIdentifier(), 'token_hash' => $this->hash($token)]);
            $browser->forceFill([
                'label' => mb_substr(($agent->browser ?? 'Unknown browser').' / '.($agent->platform ?? 'unknown device'), 0, 120),
                'first_seen_at' => $browser->exists ? $browser->first_seen_at : Date::now(),
                'last_seen_at' => Date::now(),
            ])->save();
        } catch (Throwable) {
            return;
        }

        $secure = $this->settings->get('session.secure');
        Cookie::queue(Cookie::make(self::Cookie, $token, 60 * 24 * 400, null, null, is_bool($secure) ? $secure : null, true, false, 'lax'));
    }

    private function hash(string $token): string
    {
        return hash_hmac('sha256', 'gcego-sentinel:browser|'.$token, AppKey::get($this->settings));
    }
}
