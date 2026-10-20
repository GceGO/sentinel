<?php

declare(strict_types=1);

use Gcego\Sentinel\Livewire\BrowserSessions;
use Gcego\Sentinel\Livewire\TwoFactorManagement;
use Livewire\Livewire;

it('marks every layout semantically and keeps the brand panel truthful about enabled features', function (): void {
    $html = (string) $this->get('/admin/login')->getContent();

    expect($html)->toContain('data-sentinel-layout="split"')
        ->toContain(__('gcego-sentinel::messages.brand.highlights.two_factor_passkeys'))
        ->toContain(__('gcego-sentinel::messages.brand.highlights.sessions'));

    $this->reboot(['gcego-sentinel.features.passkeys' => false, 'gcego-sentinel.features.two_factor' => false]);
    $html = (string) $this->get('/admin/login')->getContent();

    expect($html)->not->toContain(__('gcego-sentinel::messages.brand.highlights.two_factor_passkeys'))
        ->not->toContain(__('gcego-sentinel::messages.brand.highlights.two_factor'))
        ->not->toContain('data-sentinel-passkey-login');
});

it('does not repeat a field\'s error in the summary, and counts invalid fields', function (): void {
    $this->from('/login')->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password']);
    $html = (string) $this->get('/login')->getContent();

    expect(substr_count($html, __('gcego-sentinel::messages.login.failed')))->toBe(1)
        ->and($html)->toContain(trans_choice('gcego-sentinel::messages.errors.check_fields', 1));
});

it('shows the two-factor setup as steps driven by the real state', function (): void {
    $user = user();
    $this->actingAs($user)->withConfirmedPassword();

    $component = Livewire::test(TwoFactorManagement::class);
    $component->assertSee(__('gcego-sentinel::messages.mfa_steps.start'))->assertSeeHtml('aria-current="step"');

    $component->call('enableTwoFactorAuthentication')->assertSee(__('gcego-sentinel::messages.mfa_steps.scan'))->assertSeeHtml('<svg');

    submit($component, 'confirmTwoFactorAuthentication', ['code' => $this->twoFactorCode($user->fresh())])
        ->assertSee(__('gcego-sentinel::messages.mfa_steps.save'))
        ->assertSeeHtml('id="sentinel-recovery-codes"');

    // Once on, the steps give way to the managed state.
    Livewire::test(TwoFactorManagement::class)->assertDontSeeHtml('aria-current="step"')->assertSee(__('gcego-sentinel::messages.two_factor.regenerate'));
});

it('labels the current browser session', function (): void {
    $this->reboot(['session.driver' => 'database']);
    $user = user();
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    Livewire::test(BrowserSessions::class)->assertSee(__('gcego-sentinel::messages.sessions.current'));
});

it('renders the redesigned pages in French without missing strings', function (string $url): void {
    app()->setLocale('fr');

    $html = (string) $this->get($url)->assertOk()->getContent();

    expect($html)->not->toContain('gcego-sentinel::messages.')->toContain('lang="fr"');
})->with(['/login', '/admin/login', '/teacher/login', '/forgot-password', '/account/recovery', '/signed-out', '/session-expired']);

it('keeps sensitive values out of the page markup', function (): void {
    $html = (string) $this->get('/login')->getContent();

    expect($html)->not->toContain('localStorage.setItem("password')
        ->and(preg_match('/<script(?![^>]*\bsrc=)/', $html))->toBe(0);
});
