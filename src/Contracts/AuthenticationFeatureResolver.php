<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Panels\AuthenticationPanel;

/**
 * Whether a feature is really available: Sentinel's switch AND its backend
 * (the feature switch, the session driver, the registration policy, the user model). A
 * feature that is not available is never shown.
 */
interface AuthenticationFeatureResolver
{
    public function enabled(Feature $feature, ?AuthenticationPanel $panel = null): bool;

    /**
     * Whether a page can be shown: its own switch (gcego-sentinel.pages) and its feature.
     */
    public function page(AuthenticationPage $page, ?AuthenticationPanel $panel = null): bool;

    /**
     * @return list<Feature>
     */
    public function available(?AuthenticationPanel $panel = null): array;
}
