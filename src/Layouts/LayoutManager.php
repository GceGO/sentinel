<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Layouts;

use Gcego\Sentinel\Contracts\AuthenticationLayout;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelServiceProvider;

/**
 * The authentication layouts: the four built-in ones, those configured in
 * gcego-sentinel.layouts, and those registered at runtime:
 *
 *     app(LayoutManager::class)->register(new ViewLayout('school', 'auth.layouts.school'));
 */
final class LayoutManager
{
    public const array BuiltIn = ['centered', 'split', 'minimal', 'branded'];

    /**
     * @var array<string, AuthenticationLayout>
     */
    private array $layouts = [];

    public function __construct(private readonly SentinelConfig $config)
    {
        foreach (self::BuiltIn as $name) {
            $this->layouts[$name] = new ViewLayout($name, SentinelServiceProvider::Views.'::layouts.'.$name);
        }

        foreach ($config->layouts as $name => $view) {
            $this->layouts[$name] = new ViewLayout($name, $view);
        }
    }

    public function register(AuthenticationLayout $layout, bool $override = false): void
    {
        $name = $layout->name();

        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $name) !== 1) {
            throw AuthenticationConfigurationException::invalid('layout "'.mb_substr($name, 0, 60).'"', 'use a lowercase name such as "school".');
        }

        if (isset($this->layouts[$name]) && ! $override) {
            throw AuthenticationConfigurationException::invalid('layout "'.$name.'"', 'it is already registered (pass override: true to replace it).');
        }

        $this->layouts[$name] = $layout;
    }

    public function has(string $name): bool
    {
        return isset($this->layouts[$name]);
    }

    /**
     * The named layout, or the configured default when the name is unknown or null.
     */
    public function get(?string $name = null): AuthenticationLayout
    {
        return $this->layouts[$name ?? $this->config->layout] ?? $this->layouts[$this->config->layout] ?? $this->layouts['split'];
    }

    /**
     * @return array<string, AuthenticationLayout>
     */
    public function all(): array
    {
        return $this->layouts;
    }
}
