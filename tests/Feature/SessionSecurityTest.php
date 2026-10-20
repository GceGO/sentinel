<?php

declare(strict_types=1);

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Events\LogoutCompleted;
use Gcego\Sentinel\Events\SessionExpired;
use Gcego\Sentinel\Livewire\UpdatePasswordForm;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

function signIn(): User
{
    $user = user();
    test()->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');

    return $user;
}

it('records when the session signed in and how', function (): void {
    signIn();

    expect(session(SessionKeys::AuthenticatedAt))->toBeInt()
        ->and(session(SessionKeys::LastActivity))->toBeInt()
        ->and(session(SessionKeys::Assurance))->toBe('single')
        ->and(session(SessionKeys::PasswordFingerprint))->toBeString()->not->toContain('$2y$');
});

it('ends a session after the idle timeout', function (): void {
    $this->reboot(['gcego-sentinel.sessions.idle_timeout' => 15]);
    Event::fake([SessionExpired::class]);
    signIn();

    $this->travel(10)->minutes();
    $this->get('/dashboard')->assertOk();
    $this->travel(10)->minutes();
    $this->get('/dashboard')->assertOk();

    $this->travel(16)->minutes();
    $this->get('/dashboard')->assertRedirect('/session-expired');
    $this->assertGuest();
    Event::assertDispatched(SessionExpired::class, fn (SessionExpired $event): bool => $event->reason === 'idle');
});

it('ends a session at its absolute lifetime, whatever the activity', function (): void {
    $this->reboot(['gcego-sentinel.sessions.absolute_timeout' => 60]);
    signIn();

    foreach (range(1, 5) as $step) {
        $this->travel(10)->minutes();
        $this->get('/dashboard')->assertOk();
    }

    $this->travel(11)->minutes();
    $this->get('/dashboard')->assertRedirect('/session-expired');
    $this->assertGuest();
});

it('ends a session when the password changes elsewhere, on any session driver', function (): void {
    Event::fake([SessionExpired::class]);
    $user = signIn();

    // Changed by another device or an administrator, not in this session.
    $user->forceFill(['password' => Hash::make('Changed-Elsewhere-99')])->save();
    app('auth')->forgetGuards();   // a new request reloads the user, as in production

    $this->getJson('/dashboard')->assertUnauthorized();
    $this->assertGuest();
    Event::assertDispatched(SessionExpired::class, fn (SessionExpired $event): bool => $event->reason === 'password_changed');
});

it('signs every session out everywhere, and is safe to repeat', function (): void {
    Event::fake([LogoutCompleted::class]);
    $user = signIn();

    $this->post('/logout', ['everywhere' => '1'])->assertRedirect('/signed-out');
    $this->assertGuest();
    Event::assertDispatched(LogoutCompleted::class, fn (LogoutCompleted $event): bool => $event->everywhere);
    expect(app(SessionSecurity::class)->epoch($user))->toBeGreaterThan(0);

    // A session that signed in before the epoch (another device) is ended on its next request.
    $this->actingAs($user)->withSession([SessionKeys::AuthenticatedAt => time() - 60, SessionKeys::Epoch => 0]);
    $this->get('/dashboard')->assertRedirect('/session-expired');
    $this->assertGuest();

    // Signing out again (or as a guest) is harmless.
    $this->post('/logout')->assertRedirect('/signed-out');
});

it('deletes every stored session of the account when signing out everywhere', function (): void {
    $this->reboot(['session.driver' => 'database']);
    $user = signIn();
    DB::table('sessions')->insert(['id' => str_repeat('z', 40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);

    $this->post('/logout', ['everywhere' => '1']);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('keeps the current session after its own password change while others end', function (): void {
    $user = signIn();
    $security = app(SessionSecurity::class);
    $before = $security->epoch($user);

    submit(Livewire\Livewire::test(UpdatePasswordForm::class), 'updatePassword', ['state.current_password' => PASSWORD, 'state.password' => 'A-new-Password-42', 'state.password_confirmation' => 'A-new-Password-42'])
        ->assertHasNoErrors();
    app('auth')->forgetGuards();

    expect($security->epoch($user))->toBeGreaterThan($before);
    $this->get('/dashboard')->assertOk();
});

it('starts tracking sessions it did not sign in itself (remember-me, older sessions)', function (): void {
    $user = user();
    $this->actingAs($user);

    $this->get('/dashboard')->assertOk();

    expect(session(SessionKeys::AuthenticatedAt))->toBeInt()
        ->and(session(SessionKeys::PasswordFingerprint))->toBeString();
});

it('requires a fresh password confirmation for step-up routes', function (): void {
    Route::middleware(['web', 'auth', 'sentinel.step-up:300'])->get('/danger', static fn (): string => 'Danger zone');
    Route::middleware(['web', 'auth', 'sentinel.step-up:300'])->post('/danger', static fn (): string => 'Done');
    $user = user();
    $this->actingAs($user);

    $this->get('/danger')->assertRedirect('/user/confirm-password');
    $this->postJson('/danger')->assertStatus(423);

    $this->post('/user/confirm-password', ['password' => PASSWORD])->assertRedirect('/danger');
    $this->get('/danger')->assertOk()->assertSee('Danger zone');

    $this->travel(6)->minutes();
    $this->get('/danger')->assertRedirect('/user/confirm-password');
});

it('refuses a wrong password at confirmation and reports confirmation status as JSON', function (): void {
    $this->actingAs(user());

    $this->getJson('/user/confirmed-password-status')->assertExactJson(['confirmed' => false]);
    $this->post('/user/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->postJson('/user/confirm-password', ['password' => PASSWORD])->assertCreated();
    $this->getJson('/user/confirmed-password-status')->assertExactJson(['confirmed' => true]);
});

it('ends only this device when a session times out, keeping remember-me elsewhere', function (): void {
    $this->reboot(['gcego-sentinel.sessions.idle_timeout' => 15]);
    $user = user();
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD, 'remember' => '1']);
    $token = $user->fresh()->remember_token;
    expect($token)->not->toBeNull();

    $this->travel(16)->minutes();
    $this->get('/dashboard')->assertRedirect('/session-expired');

    // Another device's remember-me cookie (same token) still works.
    expect($user->fresh()->remember_token)->toBe($token);
});
