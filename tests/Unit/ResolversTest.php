<?php

declare(strict_types=1);

use Gcego\Sentinel\Accounts\AccountState;
use Gcego\Sentinel\Accounts\AttributeAccountStateResolver;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Layouts\LayoutManager;
use Gcego\Sentinel\Layouts\ViewLayout;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\UserAgent;
use Gcego\Sentinel\Support\PasswordGuidance;
use Gcego\Sentinel\Support\StatusMessages;

it('enables a feature only when Sentinel and its backend both do', function (): void {
    $features = app(AuthenticationFeatureResolver::class);

    expect($features->enabled(Feature::Login))->toBeTrue()
        ->and($features->enabled(Feature::TwoFactorAuthentication))->toBeTrue()
        ->and($features->enabled(Feature::Registration))->toBeFalse()
        ->and($features->enabled(Feature::Registration, app(SentinelManager::class)->panels()->find('student')))->toBeTrue()
        ->and($features->enabled(Feature::BrowserSessions))->toBeFalse()
        ->and($features->page(AuthenticationPage::SessionExpired))->toBeTrue();

    $this->reboot([
        'gcego-sentinel.features' => ['two_factor' => false, 'password_reset' => false, 'profile' => false, 'passkeys' => false],
        'session.driver' => 'database',
    ]);
    $features = app(AuthenticationFeatureResolver::class);

    expect($features->enabled(Feature::TwoFactorAuthentication))->toBeFalse()
        ->and($features->enabled(Feature::Passkeys))->toBeFalse()
        ->and($features->enabled(Feature::PasswordReset))->toBeFalse()
        ->and($features->enabled(Feature::ProfileInformation))->toBeFalse()
        ->and($features->enabled(Feature::BrowserSessions))->toBeTrue();
});

it('switches pages off from configuration', function (): void {
    $this->reboot(['gcego-sentinel.pages.forgot_password' => false]);

    expect(app(AuthenticationFeatureResolver::class)->enabled(Feature::PasswordReset))->toBeFalse();
    $this->get('/login')->assertDontSee('/forgot-password', false);
    // The page is not rendered (the application may provide its own).
    $this->get('/forgot-password')->assertNotFound();
});

it('resolves layouts, falling back to the default, and registers custom ones', function (): void {
    $layouts = app(LayoutManager::class);

    expect(array_keys($layouts->all()))->toBe(['centered', 'split', 'minimal', 'branded'])
        ->and($layouts->get('minimal')->view())->toBe('gcego-sentinel::layouts.minimal')
        ->and($layouts->get('unknown')->name())->toBe('split');

    $layouts->register(new ViewLayout('school', 'auth.school'));
    expect($layouts->get('school')->view())->toBe('auth.school')
        ->and(fn () => $layouts->register(new ViewLayout('school', 'other')))->toThrow(AuthenticationConfigurationException::class);
});

it('reads account states from the configured attribute, enums included', function (): void {
    $resolver = new AttributeAccountStateResolver(SentinelConfig::fromArray(['account_states' => ['attribute' => 'status', 'suspended' => ['suspended', 'locked'], 'disabled' => ['closed']]]));

    expect($resolver->state(user(['status' => 'active'])))->toBe(AccountState::Active)
        ->and($resolver->state(user(['status' => 'locked'])))->toBe(AccountState::Suspended)
        ->and($resolver->state(user(['status' => 'closed'])))->toBe(AccountState::Disabled)
        ->and((new AttributeAccountStateResolver(SentinelConfig::fromArray([])))->state(user(['status' => 'locked'])))->toBe(AccountState::Active);
});

it('describes user agents without inventing devices', function (string $agent, ?string $browser, ?string $platform, bool $mobile): void {
    $parsed = UserAgent::parse($agent);

    expect([$parsed->browser, $parsed->platform, $parsed->mobile])->toBe([$browser, $platform, $mobile]);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0', 'Edge', 'Windows', false],
    ['Mozilla/5.0 (Linux; Android 14; SM-A146B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36', 'Chrome', 'Android', true],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', 'Safari', 'macOS', false],
    ['curl/8.0', null, null, false],
    ['', null, null, false],
]);

it('translates Sentinel status keys and passes broker messages through', function (): void {
    expect(StatusMessages::for('verification-link-sent'))->toBe(__('gcego-sentinel::messages.status.verification-link-sent'))
        ->and(StatusMessages::for('We have emailed your password reset link.'))->toBe('We have emailed your password reset link.')
        ->and(StatusMessages::for(['x']))->toBeNull();
});

it('describes the password policy: length, no composition rules', function (): void {
    $this->reboot(['gcego-sentinel.passwords.min_length' => 14, 'gcego-sentinel.passwords.uncompromised' => true]);

    expect(PasswordGuidance::rules())->toMatchArray(['min' => 14, 'mixedCase' => false, 'numbers' => false, 'symbols' => false, 'uncompromised' => true])
        ->and(array_keys(PasswordGuidance::lines()))->toBe(['min', 'uncompromised']);
});

it('speaks French', function (): void {
    app()->setLocale('fr');

    expect(AuthenticationPage::Login->title())->toBe('Connexion');
    $this->get('/login')->assertSee('Mot de passe oublié ?')->assertSee('Se connecter')->assertSee('lang="fr"', false);
});
