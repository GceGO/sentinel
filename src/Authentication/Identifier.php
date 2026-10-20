<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Authentication;

use Illuminate\Support\Str;

/**
 * Normalisation of a sign-in identifier: trimmed, Unicode NFKC when intl
 * is available, and lowercased when configured, so "  Ada@Example.COM"
 * and "ada@example.com" are the same account and the same throttle key.
 */
final class Identifier
{
    public static function normalize(string $value, bool $lowercase): string
    {
        $value = trim($value);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            $value = is_string($normalized) ? $normalized : $value;
        }

        return $lowercase ? Str::lower($value) : $value;
    }
}
