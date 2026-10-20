<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

/**
 * A page layout: a Blade view that receives `$context` (AuthenticationContext)
 * and `$slot` (the page body). Layouts change presentation only; every
 * layout renders the same forms posting to the same Sentinel endpoints.
 */
interface AuthenticationLayout
{
    public function name(): string;

    public function view(): string;
}
