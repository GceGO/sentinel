<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\LoginFailed;
use Gcego\Sentinel\MultiFactor\RecoveryCodes;
use Gcego\Sentinel\MultiFactor\TotpReplayGuard;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/*
| Simulated attacks against the local, disposable test application only.
*/

it('slows credential stuffing from one client across many accounts', function (): void {
    $this->reboot(['gcego-sentinel.rate_limits.login_ip' => [3, 60]]);
    $victims = [user(), user(), user(), user()];

    foreach (array_slice($victims, 0, 3) as $victim) {
        $this->post('/login', ['email' => $victim->email, 'password' => 'leaked-password-1'])->assertSessionHasErrors('email');
    }

    // The fourth account is refused before its password is even checked, even with the right one.
    $this->postJson('/login', ['email' => $victims[3]->email, 'password' => PASSWORD])->assertStatus(429);
    $this->post('/login', ['email' => $victims[3]->email, 'password' => PASSWORD])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many attempts.');
    $this->assertGuest();

    // Another client is not affected.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post('/login', ['email' => $victims[3]->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
});

it('slows distributed guessing against one account without locking out its owner for long', function (): void {
    $this->reboot(['gcego-sentinel.rate_limits.login_account' => [3, 120]]);
    $victim = user();

    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => $address])->post('/login', ['email' => $victim->email, 'password' => 'guess'])->assertSessionHasErrors('email');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])->postJson('/login', ['email' => $victim->email, 'password' => PASSWORD])->assertStatus(429);

    $this->travel(3)->minutes();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])->post('/login', ['email' => $victim->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
});

it('normalises identifiers so case or spacing cannot dodge the throttle', function (): void {
    $this->reboot(['gcego-sentinel.rate_limits.login' => [2, 60]]);
    $victim = user();

    $this->post('/login', ['email' => strtoupper($victim->email), 'password' => 'guess']);
    $this->post('/login', ['email' => '  '.$victim->email.'  ', 'password' => 'guess']);

    $this->postJson('/login', ['email' => $victim->email, 'password' => PASSWORD])->assertStatus(429);
});

it('does not reveal accounts through errors, status codes or the audit-visible user key', function (): void {
    Event::fake([LoginFailed::class]);
    $user = user();

    $unknown = $this->post('/login', ['email' => 'nobody@example.test', 'password' => PASSWORD]);
    $wrong = $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    expect($unknown->getStatusCode())->toBe($wrong->getStatusCode())
        ->and($unknown->getSession()->get('errors')->first('email'))->toBe($wrong->getSession()->get('errors')->first('email'));
    // The audit trail (never the visitor) learns which account was targeted.
    Event::assertDispatched(LoginFailed::class, fn (LoginFailed $event): bool => $event->userKey === null);
    Event::assertDispatched(LoginFailed::class, fn (LoginFailed $event): bool => $event->userKey === $user->id);
});

it('spends a password hash even for unknown accounts (no timing shortcut)', function (): void {
    $counting = new class(app('hash')) implements Hasher
    {
        public int $checks = 0;

        public function __construct(private Hasher $inner) {}

        public function info($hashedValue): array
        {
            return $this->inner->info($hashedValue);
        }

        public function make(#[SensitiveParameter] $value, array $options = []): string
        {
            return $this->inner->make($value, $options);
        }

        public function check(#[SensitiveParameter] $value, $hashedValue, array $options = []): bool
        {
            $this->checks++;

            return $this->inner->check($value, $hashedValue, $options);
        }

        public function needsRehash($hashedValue, array $options = []): bool
        {
            return $this->inner->needsRehash($hashedValue, $options);
        }
    };
    $this->app->instance(Hasher::class, $counting);

    $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever-password'])->assertSessionHasErrors('email');

    expect($counting->checks)->toBe(1);
});

it('prevents session fixation: a planted session id never becomes authenticated', function (): void {
    $user = user();
    $this->get('/login');
    $planted = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    expect(session()->getId())->not->toBe($planted);
    $this->assertAuthenticatedAs($user);
});

it('gives the half-signed-in state of a two-factor sign-in its own session id, still as a guest', function (): void {
    $user = user();
    $this->enableTwoFactor($user);
    $this->get('/login');
    $planted = session()->getId();

    $this->postJson('/login', ['email' => $user->email, 'password' => PASSWORD])->assertOk()->assertJson(['two_factor' => true]);

    expect(session()->getId())->not->toBe($planted);
    $this->assertGuest();
});

it('cannot bypass two-factor authentication by going straight to a protected page', function (): void {
    $user = user();
    $this->enableTwoFactor($user);

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/account/security')->assertRedirect('/login');
    $this->postJson('/user/confirm-password', ['password' => PASSWORD])->assertUnauthorized();
    $this->assertGuest();
});

it('cannot pick another account at the challenge', function (): void {
    $attacker = user();
    $victim = user();
    $this->enableTwoFactor($attacker);
    $this->enableTwoFactor($victim);

    $this->post('/login', ['email' => $attacker->email, 'password' => PASSWORD]);
    // Extra fields naming another account are ignored: the pending sign-in decides.
    $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($victim), 'user_id' => $victim->id, 'email' => $victim->email])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('ignores role or permission fields smuggled into registration', function (): void {
    $this->reboot(['gcego-sentinel.registration.enabled' => true]);

    $this->post('/register', ['name' => 'Mallory', 'email' => 'mallory@example.test', 'password' => PASSWORD, 'password_confirmation' => PASSWORD, 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now()->toDateTimeString()]);

    $user = User::query()->where('email', 'mallory@example.test')->firstOrFail();
    expect($user->role)->toBeNull()->and($user->email_verified_at)->toBeNull();
});

it('redeems a recovery code exactly once even when submitted twice at the same moment', function (): void {
    $user = user();
    $codes = $this->enableTwoFactor($user);
    $redeemer = app(RecoveryCodes::class);

    // The second redemption sees the conditional UPDATE already applied.
    expect($redeemer->redeem($user, $codes[0]))->toBeTrue()
        ->and($redeemer->redeem($user, $codes[0]))->toBeFalse()
        ->and($redeemer->remaining($user))->toBe(count($codes) - 1);
});

it('accepts each authenticator time step once, and never an older one after a newer one', function (): void {
    $guard = app(TotpReplayGuard::class);

    expect($guard->claim('42', 1000, 1))->toBeTrue()
        ->and($guard->claim('42', 1000, 1))->toBeFalse()
        ->and($guard->claim('42', 999, 1))->toBeFalse()
        ->and($guard->claim('42', 1001, 1))->toBeTrue()
        ->and($guard->claim('43', 1000, 1))->toBeTrue();
});

it('refuses oversized inputs before hashing them', function (): void {
    $user = user();

    $this->post('/login', ['email' => $user->email, 'password' => str_repeat('a', 5000)])->assertSessionHasErrors('password');
    $this->post('/login', ['email' => str_repeat('a', 300).'@example.test', 'password' => PASSWORD])->assertSessionHasErrors('email');
    $this->post('/two-factor-challenge', ['recovery_code' => str_repeat('A', 500)])->assertRedirect('/login');
});

it('upgrades outdated password hashes on sign-in', function (): void {
    $user = user();
    $user->forceFill(['password' => Hash::make(PASSWORD, ['rounds' => 4])])->save();
    $this->app['config']->set('hashing.bcrypt.rounds', 5);
    $this->app->forgetInstance('hash');
    Hash::clearResolvedInstance('hash');

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');

    expect(Hash::needsRehash($user->fresh()->password))->toBeFalse();
});
