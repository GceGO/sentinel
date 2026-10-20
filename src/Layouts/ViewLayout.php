<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Layouts;

use Gcego\Sentinel\Contracts\AuthenticationLayout;

/**
 * A layout that is one Blade view.
 */
final readonly class ViewLayout implements AuthenticationLayout
{
    public function __construct(private string $name, private string $view) {}

    public function name(): string
    {
        return $this->name;
    }

    public function view(): string
    {
        return $this->view;
    }
}
