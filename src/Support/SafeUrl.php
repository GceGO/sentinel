<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

/**
 * URL checks for configured branding assets and links.
 */
final class SafeUrl
{
    /**
     * A root-relative path ("/images/logo.svg") or an https URL. Never
     * protocol-relative, javascript:, data: or other schemes.
     */
    public static function asset(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme) && strtolower($scheme) === 'https' && is_string($host) && $host !== '' ? $url : null;
    }

    /**
     * A link: what asset() accepts, plus http URLs and mailto: addresses.
     */
    public static function link(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('/^mailto:[^\s<>"\'\\\\`]{3,320}$/i', $url) === 1) {
            return $url;
        }

        if (preg_match('/^http:\/\/[^\s<>"\'\\\\`\/]+(?:\/[^\s<>"\'\\\\`]*)?$/i', $url) === 1 && strlen($url) <= 2048) {
            return $url;
        }

        return self::asset($url);
    }

    /**
     * A #rgb or #rrggbb color, lowercased and expanded to #rrggbb.
     */
    public static function color(string $color): ?string
    {
        $color = strtolower(trim($color));

        if (preg_match('/^#[0-9a-f]{3}$/', $color) === 1) {
            return '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : null;
    }
}
