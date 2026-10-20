<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\AccountAccessDenied;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

it('renders the login page without queries and never cached', function (): void {
    $response = null;
    $queries = queriesDuring(function () use (&$response): void {
        $response = $this->get('/login');
    });

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('<form method="POST" action="http://localhost/login"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('autocomplete="username"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('name="remember"', false)
        ->assertSee('href="http://localhost/forgot-password"', false)
        ->assertDontSee('/register', false);

    expect($queries)->toBe([]);
});

it('signs in with valid credentials, regenerates the session and goes home', function (): void {
    $user = user();
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before);
});

it('accepts the email in any case (identifiers are normalised)', function (): void {
    $user = user(['email' => 'mixed@example.test']);

    $this->post('/login', ['email' => 'MIXED@Example.test', 'password' => PASSWORD])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('gives the same generic error for a wrong password and an unknown account', function (): void {
    $user = user();

    $wrong = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $wrong->assertRedirect('/login')->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();

    $unknown = $this->from('/login')->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password']);
    $unknown->assertRedirect('/login')->assertSessionHasErrors(['email' => __('auth.failed')]);

    $page = $this->get('/login');
    $page->assertSee(__('auth.failed'))->assertSee('aria-invalid="true"', false)->assertSee('data-sentinel-error-summary', false);
});

it('keeps people signed in with remember me', function (): void {
    $user = user();

    $response = $this->post('/login', ['email' => $user->email, 'password' => PASSWORD, 'remember' => '1']);

    $response->assertRedirect('/dashboard')->assertCookie(Auth::guard('web')->getRecallerName());
});

it('rate limits login attempts and shows the lockout without revealing the account', function (): void {
    $user = user();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $locked = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    $locked->assertRedirect('/login')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many attempts')
        ->and(session('errors')->first('email'))->not->toContain($user->email);
    $this->assertGuest();
});

it('goes to a safe intended URL and ignores external ones', function (): void {
    $user = user();

    $this->get('/dashboard/reports')->assertRedirect('/login');
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard/reports');

    Auth::logout();
    session()->put('url.intended', 'https://evil.example/steal');
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
});

it('refuses suspended and disabled accounts once the password is right, generically', function (string $status): void {
    Event::fake([AccountAccessDenied::class]);
    $user = user(['status' => $status]);

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => PASSWORD])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => __('gcego-sentinel::messages.account.unavailable_login')]);
    $this->assertGuest();

    // A wrong password gets the ordinary error: the state is only revealed to the owner.
    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors(['email' => __('auth.failed')]);

    Event::assertDispatched(AccountAccessDenied::class, fn (AccountAccessDenied $event): bool => $event->userKey === $user->id && $event->state->value === $status && $event->stage === 'login');
})->with(['suspended', 'disabled']);

it('signs out an account suspended during its session on the next request', function (): void {
    $user = user();
    $this->actingAs($user)->get('/account/security')->assertOk();

    $user->forceFill(['status' => 'suspended'])->save();

    $this->get('/account/security')->assertRedirect('/account/unavailable');
    $this->assertGuest();
    $this->get('/account/unavailable')->assertOk()->assertSee(__('gcego-sentinel::messages.account.suspended_heading'));
});

it('signs out and shows the signed-out page', function (): void {
    $user = user();
    $this->actingAs($user);

    $this->post('/logout')->assertRedirect('/signed-out');

    $this->assertGuest();
    $this->get('/signed-out')->assertOk()->assertSee(__('gcego-sentinel::messages.logged_out.heading'));
});

it('answers JSON clients with the resolved destination', function (): void {
    $user = user();

    $this->postJson('/login', ['email' => $user->email, 'password' => PASSWORD])
        ->assertOk()
        ->assertExactJson(['two_factor' => false, 'redirect' => '/dashboard']);
});
