<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\PasswordResetCompleted;
use Gcego\Sentinel\Notifications\PasswordChangedNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('renders the forgot-password page and sends the reset link through the password broker', function (): void {
    Notification::fake();
    $user = user();

    $this->get('/forgot-password')->assertOk()->assertSee('action="http://localhost/forgot-password"', false)->assertSee(__('gcego-sentinel::messages.back_to_login'));

    $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', __('gcego-sentinel::messages.forgot.sent'));

    Notification::assertSentTo($user, ResetPassword::class);
    $this->get('/forgot-password')->assertSee(__('gcego-sentinel::messages.forgot.sent'));
});

it('answers unknown and throttled addresses exactly like known ones', function (): void {
    Notification::fake();
    $user = user();

    $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
    $again = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.test']);

    foreach ([$known, $again, $unknown] as $response) {
        $response->assertRedirect('/forgot-password')->assertSessionHas('status', __('gcego-sentinel::messages.forgot.sent'))->assertSessionHasNoErrors();
    }

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('still validates the email field', function (): void {
    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
});

it('throttles reset link requests', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'someone@example.test']);
    }

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'someone@example.test'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many attempts');
});

it('resets the password with a valid token, invalidates the token and returns to sign in', function (): void {
    $user = user();
    $token = Password::broker()->createToken($user);

    $page = $this->get('/reset-password/'.$token.'?email='.urlencode($user->email));
    $page->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('name="token" value="'.$token.'"', false)
        ->assertSee('value="'.$user->email.'"', false);

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42'])
        ->assertRedirect('/login')
        ->assertSessionHas('status', __('gcego-sentinel::messages.reset.done'));

    expect(Hash::check('A-new-Password-42', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);

    $this->from('/reset-password/'.$token)->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Another-Pass-77', 'password_confirmation' => 'Another-Pass-77'])
        ->assertSessionHasErrors('email');
    expect(Hash::check('A-new-Password-42', $user->fresh()->password))->toBeTrue();
});

it('rejects invalid and expired tokens and weak passwords', function (): void {
    $user = user();
    $token = Password::broker()->createToken($user);

    $this->from('/reset-password/x')->post('/reset-password', ['token' => 'invalid', 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42'])
        ->assertSessionHasErrors('email');

    $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');

    $this->travel(61)->minutes();
    $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42'])
        ->assertSessionHasErrors('email');

    expect(Hash::check(PASSWORD, $user->fresh()->password))->toBeTrue();
});

it('returns to the panel\'s sign-in page after a reset started there', function (): void {
    $user = user(['role' => 'admin']);
    $token = Password::broker()->createToken($user);
    $this->get('/admin/login');

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42'])
        ->assertRedirect('/admin/login');
});

it('gives an unknown address the same answer as a wrong token on reset', function (): void {
    $user = user();
    $token = Password::broker()->createToken($user);

    $unknown = $this->from('/reset-password/x')->post('/reset-password', ['token' => $token, 'email' => 'nobody@example.test', 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42']);
    $wrong = $this->from('/reset-password/x')->post('/reset-password', ['token' => 'wrong', 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42']);

    expect($unknown->getSession()->get('errors')->first('email'))->toBe(__('gcego-sentinel::messages.reset.invalid'))
        ->and($wrong->getSession()->get('errors')->first('email'))->toBe(__('gcego-sentinel::messages.reset.invalid'));
});

it('ends every session of the account and never signs anyone in on reset', function (): void {
    $this->reboot(['session.driver' => 'database']);
    Event::fake([PasswordResetCompleted::class, PasswordReset::class]);
    $user = user();
    DB::table('sessions')->insert(['id' => str_repeat('a', 40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
    $token = Password::broker()->createToken($user);
    $rememberToken = $user->fresh()->remember_token;

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42']);

    $this->assertGuest();
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->remember_token)->not->toBe($rememberToken)->not->toBeNull();
    Event::assertDispatched(PasswordResetCompleted::class, fn (PasswordResetCompleted $event): bool => $event->sessionsEnded === 1);
    Event::assertDispatched(PasswordReset::class);
});

it('emails the owner after a password reset', function (): void {
    Notification::fake();
    $user = user();
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-new-Password-42', 'password_confirmation' => 'A-new-Password-42']);

    Notification::assertSentTo($user, PasswordChangedNotification::class);
});
