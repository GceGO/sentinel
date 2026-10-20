<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Exceptions;

use Gcego\Sentinel\Features\Feature;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A page or operation needs a feature that Sentinel has disabled (404, never a broken page).
 */
final class AuthenticationFeatureUnavailableException extends NotFoundHttpException implements SentinelException
{
    public static function for(Feature $feature): self
    {
        return new self(sprintf('The "%s" authentication feature is not available.', $feature->value));
    }
}
