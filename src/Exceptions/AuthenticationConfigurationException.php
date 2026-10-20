<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Exceptions;

use InvalidArgumentException;

/**
 * A gcego-sentinel configuration value (or a value an extension supplied) is invalid.
 */
final class AuthenticationConfigurationException extends InvalidArgumentException implements SentinelException
{
    public static function invalid(string $key, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $key, $reason));
    }
}
