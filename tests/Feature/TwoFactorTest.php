<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\LoginSucceeded;
use Gcego\Sentinel\Events\RecoveryCodesRegenerated;
use Gcego\Sentinel\Events\RecoveryCodeUsed;
use Gcego\Sentinel\Events\TwoFactorChallengeFailed;
use Gcego\Sentinel\Events\TwoFactorDisabled;
use Gcego\Sentinel\Events\TwoFactorEnabled;
use Gcego\Sentinel\Livewire\TwoFactorManagement;
use Gcego\Sentinel\MultiFactor\RecoveryCode;
use Gcego\Sentinel\MultiFactor\RecoveryCodes;
use Gcego\Sentinel\Notifications\SecurityAlertNotification;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * A user with confirmed two-factor authentication; $codes receives the recovery codes.
 *
 * @param  list<string>|null  $codes
 */
function twoFactorUser(array $attributes = [], ?array &$codes = null): User
{
    $user = user($attributes);
    $codes = test()->enableTwoFactor($user);

    return $user->fresh();
}

it('challenges users with two-factor authentication and accepts a valid code', function (): void {
    $user = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->get('/two-factor-challenge')->assertOk()
        ->assertSee('autocomplete="one-time-code"', false)
        ->assertSee('inputmode="numeric"', false)
        ->assertSee('href="http://localhost/two-factor-challenge?recovery=1"', false)
        ->assertSee('href="http://localhost/account/recovery"', false);

    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($user)])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    expect(session('gcego-sentinel.assurance'))->toBe('multi');
});

it('only opens the challenge after a correct password', function (): void {
    $this->get('/two-factor-challenge')->assertRedirect('/login');
    $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect('/login');

    $user = twoFactorUser();
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $this->get('/two-factor-challenge')->assertRedirect('/login');
    $this->assertGuest();
});

it('rejects invalid codes', function (): void {
    Event::fake([TwoFactorChallengeFailed::class]);
    $user = twoFactorUser();
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect('/two-factor-challenge')->assertSessionHasErrors('code');

    $this->assertGuest();
    Event::assertDispatched(TwoFactorChallengeFailed::class, fn (TwoFactorChallengeFailed $event): bool => $event->userKey === $user->id && ! $event->exhausted);
});

it('refuses a code that was already used (replay)', function (): void {
    $user = twoFactorUser();
    $code = $this->twoFactorCode($user);

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/dashboard');
    $this->post('/logout');

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
    $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('asks for the password again after too many wrong codes', function (): void {
    $this->reboot(['gcego-sentinel.two_factor.challenge_attempts' => 2]);
    $user = twoFactorUser();
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/two-factor-challenge', ['code' => '111111'])->assertRedirect('/login')->assertSessionHasErrors('email');

    // The pending sign-in is gone: even the right code is not accepted now.
    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($user)])->assertRedirect('/login');
    $this->assertGuest();
});

it('expires a pending sign-in', function (): void {
    $user = twoFactorUser();
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    $this->travel(6)->minutes();

    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($user)])->assertRedirect('/login');
    $this->assertGuest();
});

it('accepts a recovery code once, and stores codes only as hashes', function (): void {
    Notification::fake();
    Event::fake([RecoveryCodeUsed::class, LoginSucceeded::class]);
    $user = twoFactorUser(codes: $codes);
    $code = $codes[0];

    expect($codes)->toHaveCount(8)->each->toMatch('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/');
    expect(RecoveryCode::query()->where('user_id', $user->id)->pluck('code_hash')->all())->each->not->toContain($code);

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
    $this->get('/two-factor-challenge?recovery=1')->assertOk()->assertSee('name="recovery_code"', false);
    $this->post('/two-factor-challenge', ['recovery_code' => strtolower($code)])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);

    Event::assertDispatched(RecoveryCodeUsed::class, fn (RecoveryCodeUsed $event): bool => $event->remaining === 7);
    Event::assertDispatched(LoginSucceeded::class, fn (LoginSucceeded $event): bool => $event->method === 'recovery_code');
    Notification::assertSentTo($user, SecurityAlertNotification::class, fn (SecurityAlertNotification $notification): bool => $notification->kind === 'recovery_code_used');

    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
    $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

it('keeps the panel through the challenge and still enforces panel access', function (): void {
    $admin = twoFactorUser(['role' => 'admin']);
    $this->get('/admin/login');
    $this->post('/login', ['email' => $admin->email, 'password' => PASSWORD]);
    $this->get('/two-factor-challenge')->assertSee('Administration');
    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($admin)])->assertRedirect('/admin');

    $this->post('/logout');
    $student = twoFactorUser(['role' => 'student']);
    $this->get('/admin/login');
    $this->post('/login', ['email' => $student->email, 'password' => PASSWORD]);
    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($student)])->assertRedirect('/admin/login');
    $this->assertGuest();
});

it('refuses a suspended account before the challenge, and one suspended during it', function (): void {
    $user = twoFactorUser(['status' => 'suspended']);
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertSessionHasErrors('email');
    $this->get('/two-factor-challenge')->assertRedirect('/login');

    $other = twoFactorUser();
    $this->post('/login', ['email' => $other->email, 'password' => PASSWORD])->assertRedirect('/two-factor-challenge');
    $other->forceFill(['status' => 'suspended'])->save();
    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($other)])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('enrolls after a password confirmation, confirming with a code', function (): void {
    Event::fake([TwoFactorEnabled::class]);
    Notification::fake();
    $user = user();
    $this->actingAs($user);

    $component = Livewire::test(TwoFactorManagement::class)
        ->assertSee(__('gcego-sentinel::messages.two_factor.enable'))
        ->call('startConfirmingPassword', 'enableTwoFactorAuthentication')
        ->assertSet('confirmingPassword', true);
    submit($component, 'confirmPassword', ['confirmablePassword' => 'wrong'])->assertHasErrors('confirmablePassword');
    submit($component, 'confirmPassword', ['confirmablePassword' => PASSWORD])
        ->assertSet('confirmingPassword', false)
        ->assertSet('confirmablePassword', '');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $secret = decrypt($user->two_factor_secret);
    $component->assertSee('<svg', false)->assertSee($secret)->assertSee(__('gcego-sentinel::messages.two_factor.pending'));

    submit($component, 'confirmTwoFactorAuthentication', ['code' => '000000'])->assertHasErrors('code');
    submit($component, 'confirmTwoFactorAuthentication', ['code' => $this->twoFactorCode($user)])->assertHasNoErrors()->assertSet('justConfirmed', true);

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and(RecoveryCode::query()->where('user_id', $user->id)->count())->toBe(8);
    $component->assertSee(__('gcego-sentinel::messages.two_factor.store_codes'))->assertDontSee($secret);
    expect(session('gcego-sentinel.assurance'))->toBe('multi');
    Event::assertDispatched(TwoFactorEnabled::class);
    Notification::assertSentTo($user, SecurityAlertNotification::class, fn (SecurityAlertNotification $notification): bool => $notification->kind === 'two_factor_enabled');
});

it('does not turn two-factor on until a code confirms the setup', function (): void {
    $user = user();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(TwoFactorManagement::class)->call('enableTwoFactorAuthentication');
    $this->post('/logout');

    // Pending setup: a password sign-in is not challenged and two-factor is not "enrolled".
    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
});

it('never puts secrets, codes or passwords in the component snapshot', function (): void {
    $user = twoFactorUser();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $component = Livewire::test(TwoFactorManagement::class)->call('regenerateRecoveryCodes');
    $html = $component->html();
    $snapshot = json_encode($component->snapshot);
    preg_match('/<li>([A-Z0-9]{5}(?:-[A-Z0-9]{5}){3})<\/li>/', $html, $match);

    expect($match)->toHaveCount(2);
    expect($snapshot)->not->toContain($match[1])
        ->not->toContain(decrypt($user->two_factor_secret))
        ->not->toContain(PASSWORD);

    // The next render (any other request) no longer shows them, and they can never be shown again.
    $component->call('$refresh')->assertDontSee($match[1])->assertSee(trans_choice('gcego-sentinel::messages.two_factor.remaining', 8, ['count' => 8]));
});

it('requires a recent password confirmation for recovery codes, enabling and disabling, server-side', function (string $method): void {
    $this->actingAs(twoFactorUser());

    Livewire::test(TwoFactorManagement::class)->call($method)->assertForbidden();
})->with(['regenerateRecoveryCodes', 'disableTwoFactorAuthentication', 'enableTwoFactorAuthentication']);

it('regenerates recovery codes (old ones stop working) and disables two-factor authentication', function (): void {
    Event::fake([RecoveryCodesRegenerated::class, TwoFactorDisabled::class]);
    $user = twoFactorUser(codes: $old);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(TwoFactorManagement::class)->call('regenerateRecoveryCodes');
    expect(app(RecoveryCodes::class)->redeem($user, $old[0]))->toBeFalse();

    Livewire::test(TwoFactorManagement::class)->call('disableTwoFactorAuthentication');
    expect($user->fresh()->two_factor_secret)->toBeNull()
        ->and(RecoveryCode::query()->where('user_id', $user->id)->count())->toBe(0);

    Event::assertDispatched(RecoveryCodesRegenerated::class);
    Event::assertDispatched(TwoFactorDisabled::class, fn (TwoFactorDisabled $event): bool => ! $event->administrative);
});

it('refuses to disable two-factor authentication when the user\'s permissions require it', function (): void {
    $this->reboot(['gcego-sentinel.two_factor.required_permissions' => ['security.manage']]);
    $admin = twoFactorUser(['role' => 'admin']);
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(TwoFactorManagement::class)->call('disableTwoFactorAuthentication')->assertHasErrors('two_factor');

    expect($admin->fresh()->two_factor_secret)->not->toBeNull();
});

it('rejects confirmation into actions outside the allow-list, and locked state from the browser', function (): void {
    $this->actingAs(user());

    Livewire::test(TwoFactorManagement::class)->call('startConfirmingPassword', 'mount')->assertForbidden();
    expect(fn () => Livewire::test(TwoFactorManagement::class)->set('confirmingAction', 'disableTwoFactorAuthentication'))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('accepts recovery codes hashed under a previous APP_KEY after key rotation', function (): void {
    $user = user();
    $codes = $this->enableTwoFactor($user);
    $old = config('app.key');
    config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32)), 'app.previous_keys' => [$old]]);

    expect(app(RecoveryCodes::class)->redeem($user->fresh(), $codes[0]))->toBeTrue();
});

it('rate limits inline password confirmation', function (): void {
    $this->actingAs(user());
    $component = Livewire::test(TwoFactorManagement::class)->call('startConfirmingPassword', 'enableTwoFactorAuthentication');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        submit($component, 'confirmPassword', ['confirmablePassword' => 'wrong']);
    }

    submit($component, 'confirmPassword', ['confirmablePassword' => PASSWORD])->assertHasErrors('confirmablePassword');
    expect(session('auth.password_confirmed_at'))->toBeNull();
});
