<?php

declare(strict_types=1);

use Gcego\Sentinel\Audit\SecurityEventRecord;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user, int $minutes = 60): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), ['id' => $user->id, 'hash' => sha1($user->email)]);
}

it('shows the verification notice to unverified users and sends verified users on', function (): void {
    $user = user(['email_verified_at' => null]);

    $this->actingAs($user)->get('/verified-only')->assertRedirect('/email/verify');
    $this->get('/email/verify')->assertOk()
        ->assertSee(__('gcego-sentinel::messages.verify.description', ['email' => $user->email]))
        ->assertSee('action="http://localhost/email/verification-notification"', false)
        ->assertSee('action="http://localhost/logout"', false);

    $this->flushSession();
    $this->actingAs(user())->get('/email/verify')->assertRedirect('/dashboard');
});

it('verifies through Laravel\'s signed link and rejects bad or expired signatures', function (): void {
    $user = user(['email_verified_at' => null]);
    $this->actingAs($user);

    $this->get(verificationUrl($user).'tampered')->assertForbidden();
    $this->travel(2)->hours();
    $expired = verificationUrl($user, -1);
    $this->get($expired)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->get(verificationUrl($user))->assertRedirect('/dashboard?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('returns to a safe intended page after verifying', function (): void {
    $user = user(['email_verified_at' => null]);
    $this->actingAs($user)->get('/verified-only');

    $this->get(verificationUrl($user))->assertRedirect('/verified-only?verified=1');
});

it('resends the verification email with Laravel\'s notification and throttles it', function (): void {
    Notification::fake();
    $user = user(['email_verified_at' => null]);
    $this->actingAs($user);

    $this->from('/email/verify')->post('/email/verification-notification')->assertRedirect('/email/verify')->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->get('/email/verify')->assertSee(__('gcego-sentinel::messages.status.verification-link-sent'));

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from('/email/verify')->post('/email/verification-notification');
    }

    $this->from('/email/verify')->post('/email/verification-notification')->assertRedirect('/email/verify')->assertSessionHasErrors('verification');
});

it('confirms the password for sensitive areas through the password.confirm middleware', function (): void {
    $this->actingAs(user());

    $this->get('/confirmed-only')->assertRedirect('/user/confirm-password');
    $this->get('/user/confirm-password')->assertOk()->assertSee('action="http://localhost/user/confirm-password"', false);

    $this->from('/user/confirm-password')->post('/user/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->post('/user/confirm-password', ['password' => PASSWORD])->assertRedirect('/confirmed-only');

    expect(session('auth.password_confirmed_at'))->toBeInt();
    $this->get('/confirmed-only')->assertOk();
});

it('throttles password confirmation attempts', function (): void {
    $this->actingAs(user());

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from('/user/confirm-password')->post('/user/confirm-password', ['password' => 'wrong']);
    }

    $this->from('/user/confirm-password')->post('/user/confirm-password', ['password' => PASSWORD])->assertSessionHasErrors('password');
    expect(session('errors')->first('password'))->toContain('Too many attempts');
});

it('never verifies an account other than the signed-in one', function (): void {
    $attacker = user(['email_verified_at' => null]);
    $victim = user(['email_verified_at' => null]);

    $this->actingAs($attacker)->get(verificationUrl($victim))->assertForbidden();

    expect($victim->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($attacker->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('kills links sent before an email change', function (): void {
    $user = user(['email_verified_at' => null]);
    $stale = verificationUrl($user);
    $user->forceFill(['email' => 'changed@example.test'])->save();

    $this->actingAs($user->fresh())->get($stale)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('records verification in the audit trail once, even when the link is opened twice', function (): void {
    $user = user(['email_verified_at' => null]);
    $this->actingAs($user);
    $url = verificationUrl($user);

    $this->get($url);
    $this->get($url)->assertRedirect();

    expect(SecurityEventRecord::query()->where('type', 'email.verified')->count())->toBe(1);
});
