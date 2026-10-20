<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\RegistrationCompleted;
use Gcego\Sentinel\Tests\Fixtures\Actions\CreateNewUser;
use Gcego\Sentinel\Tests\Fixtures\InvitationPolicy;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

it('keeps registration closed by default, page and endpoint alike', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])->assertNotFound();

    expect(User::query()->count())->toBe(0);
    $this->get('/login')->assertDontSee(__('gcego-sentinel::messages.login.register'));
});

it('never offers registration on administrative panels by default', function (): void {
    $this->get('/admin/register')->assertNotFound();
    $this->get('/teacher/register')->assertNotFound();
    $this->get('/admin/login')->assertDontSee('/register', false);
});

it('registers through the application\'s CreatesAccounts when enabled, without granting any role', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true]);
    Notification::fake();
    Event::fake([RegistrationCompleted::class]);

    $this->get('/register')->assertOk()
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee(__('gcego-sentinel::messages.password_rules.min', ['min' => 12]));
    $this->get('/login')->assertSee('href="http://localhost/register"', false);

    // An account that must verify its email goes to the verification notice, with the link sent.
    $this->post('/register', ['name' => 'Ada', 'email' => 'Ada@Example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])->assertRedirect('/email/verify');

    $user = User::query()->where('email', 'ada@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
    Event::assertDispatched(RegistrationCompleted::class, fn (RegistrationCompleted $event): bool => $event->userKey === $user->id);
    expect($user->role)->toBeNull()
        ->and(Gate::forUser($user)->allows('panels.admin'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('security.manage'))->toBeFalse();
});

it('reports invalid registrations: duplicate email and unconfirmed password', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true]);
    $existing = user();

    $this->from('/register')->post('/register', ['name' => 'Ada', 'email' => $existing->email, 'password' => PASSWORD, 'password_confirmation' => PASSWORD])
        ->assertRedirect('/register')->assertSessionHasErrors('email');
    $this->from('/register')->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => PASSWORD, 'password_confirmation' => 'different'])
        ->assertSessionHasErrors('password');

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
});

it('requires accepting the terms server-side when terms are configured', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true, 'gcego-sentinel.registration.terms_url' => '/terms']);

    $this->get('/register')->assertSee('name="terms"', false)->assertSee('href="/terms"', false);
    $this->from('/register')->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])
        ->assertSessionHasErrors(['terms' => __('gcego-sentinel::messages.register.terms_required')]);
    expect(User::query()->count())->toBe(0);

    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD, 'terms' => '1'])->assertRedirect('/email/verify');
});

it('passes configured extra fields to the application\'s account creator', function (): void {
    $this->reboot([
        'gcego-sentinel.registration.enabled' => true,
        'gcego-sentinel.registration.fields' => ['school' => ['label' => 'School', 'autocomplete' => 'organization', 'required' => true]],
    ]);

    $this->get('/register')->assertSee('name="school"', false)->assertSee('autocomplete="organization"', false);
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'school' => 'GBHS Limbe', 'password' => PASSWORD, 'password_confirmation' => PASSWORD]);

    expect(CreateNewUser::$received)->toMatchArray(['school' => 'GBHS Limbe'])
        ->and(CreateNewUser::$received)->not->toHaveKey('password');
});

it('lets a panel open its own registration while the public one stays closed', function (): void {
    $this->get('/student/register')->assertOk()->assertSee('Students');
    $this->get('/register')->assertNotFound();

    $this->get('/student/register');
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])
        // The new account holds no panel permission, so it is never sent into the panel.
        ->assertRedirect('/email/verify');

    expect(User::query()->where('email', 'ada@example.test')->exists())->toBeTrue();
});

it('supports invitation-only registration through a custom policy', function (): void {
    $this->reboot(['gcego-sentinel.registration.policy' => InvitationPolicy::class]);

    $this->get('/register')->assertNotFound();
    $this->withSession(['invitation' => 'valid-invitation'])->get('/register')->assertOk();
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])->assertRedirect('/email/verify');
    $this->assertAuthenticated();
});

it('sends new accounts home when email verification is off', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true, 'gcego-sentinel.features.email_verification' => false]);

    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])->assertRedirect('/dashboard');
});

it('enforces the password policy on registration: length, and never silent truncation', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true]);

    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'short-pass', 'password_confirmation' => 'short-pass'])->assertSessionHasErrors('password');
    $long = str_repeat('é', 40);
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => $long, 'password_confirmation' => $long])->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

it('throttles registration attempts per address', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true, 'gcego-sentinel.rate_limits.register' => [2, 60]]);

    $this->post('/register', ['name' => 'A', 'email' => 'a@example.test', 'password' => 'x', 'password_confirmation' => 'x']);
    $this->post('/register', ['name' => 'B', 'email' => 'b@example.test', 'password' => 'x', 'password_confirmation' => 'x']);
    $this->post('/register', ['name' => 'C', 'email' => 'c@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])
        ->assertRedirect()->assertSessionHasErrors('email');
    $this->postJson('/register', ['name' => 'C', 'email' => 'c@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD])->assertStatus(429);

    expect(User::query()->where('email', 'c@example.test')->exists())->toBeFalse();
});

it('never shows registration when its page is switched off, whatever the policy says', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true, 'gcego-sentinel.pages.register' => false]);

    $this->get('/register')->assertNotFound();
    $this->get('/login')->assertDontSee(__('gcego-sentinel::messages.login.register'));
});
