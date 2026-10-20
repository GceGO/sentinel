<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Sessions;

/**
 * A short description of a User-Agent header ("Firefox on Linux"). It is
 * what the browser claims, shown so people recognise their devices; it
 * proves nothing.
 */
final readonly class UserAgent
{
    private const array Browsers = [
        'Edge' => '/\bEdg(?:e|A|iOS)?\//',
        'Opera' => '/\b(?:OPR|Opera)\//',
        'Samsung Internet' => '/\bSamsungBrowser\//',
        'Firefox' => '/\b(?:Firefox|FxiOS)\//',
        'Chrome' => '/\b(?:Chrome|CriOS)\//',
        'Safari' => '/\bVersion\/[\d.]+.*\bSafari\//',
    ];

    private const array Platforms = [
        'iOS' => '/\b(?:iPhone|iPad|iPod)\b/',
        'Android' => '/\bAndroid\b/',
        'ChromeOS' => '/\bCrOS\b/',
        'Windows' => '/\bWindows\b/',
        'macOS' => '/\bMac OS X\b|\bMacintosh\b/',
        'Linux' => '/\bLinux\b/',
    ];

    public function __construct(public ?string $browser, public ?string $platform, public bool $mobile) {}

    public static function parse(?string $header): self
    {
        $header = mb_substr((string) $header, 0, 512);

        return new self(self::first(self::Browsers, $header), self::first(self::Platforms, $header), preg_match('/\b(?:Mobile|iPhone|iPod|Android.*Mobile)\b/', $header) === 1);
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function first(array $patterns, string $header): ?string
    {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $header) === 1) {
                return $name;
            }
        }

        return null;
    }
}
