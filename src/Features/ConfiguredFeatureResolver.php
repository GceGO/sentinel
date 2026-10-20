<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Features;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Contracts\RegistrationPolicy;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Passkeys\PasskeySupport;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Feature availability from Sentinel's switches AND their real backends:
 *
 *  - every feature needs its gcego-sentinel.features switch (and its page);
 *  - registration also needs the RegistrationPolicy for the panel;
 *  - passkeys also need laravel/passkeys and a PasskeyUser user model;
 *  - browser sessions need the "database" session driver.
 *
 * Reads configuration at call time (no caching), so tests and per-request
 * configuration changes are seen.
 */
final readonly class ConfiguredFeatureResolver implements AuthenticationFeatureResolver
{
    public function __construct(
        private SentinelConfig $config,
        private Repository $settings,
        private Container $container,
    ) {}

    public function enabled(Feature $feature, ?AuthenticationPanel $panel = null): bool
    {
        if (! $this->config->enabled) {
            return false;
        }

        return match ($feature) {
            Feature::Login => $this->config->pageEnabled(AuthenticationPage::Login),
            Feature::Registration => $this->config->pageEnabled(AuthenticationPage::Register)
                && $this->container->make(RegistrationPolicy::class)->allows($this->request(), $panel),
            Feature::PasswordReset => $this->config->passwordReset && $this->config->pageEnabled(AuthenticationPage::ForgotPassword),
            Feature::EmailVerification => $this->config->emailVerification && $this->config->pageEnabled(AuthenticationPage::VerifyEmail),
            Feature::PasswordConfirmation => $this->config->pageEnabled(AuthenticationPage::ConfirmPassword),
            Feature::TwoFactorAuthentication => $this->config->twoFactor,
            Feature::Passkeys => $this->config->passkeys && PasskeySupport::supported($this->settings, $this->config->guard),
            Feature::ProfileInformation => $this->config->profile && $this->config->profileFields !== [],
            Feature::PasswordUpdate => $this->config->passwordUpdate,
            Feature::BrowserSessions => $this->config->browserSessions && $this->settings->get('session.driver') === 'database',
            Feature::AccountSecurity => $this->config->pageEnabled(AuthenticationPage::AccountSecurity),
        };
    }

    public function available(?AuthenticationPanel $panel = null): array
    {
        return array_values(array_filter(Feature::cases(), fn (Feature $feature): bool => $this->enabled($feature, $panel)));
    }

    public function page(AuthenticationPage $page, ?AuthenticationPanel $panel = null): bool
    {
        if (! $this->config->enabled || ! $this->config->pageEnabled($page)) {
            return false;
        }

        $feature = $page->feature();

        return $feature === null || $this->enabled($feature, $panel);
    }

    private function request(): Request
    {
        $request = $this->container->make('request');

        return $request instanceof Request ? $request : Request::capture();
    }
}
