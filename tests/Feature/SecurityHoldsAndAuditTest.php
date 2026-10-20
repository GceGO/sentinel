<?php

declare(strict_types=1);

use Gcego\Sentinel\Accounts\AccountAccess;
use Gcego\Sentinel\Accounts\AccountState;
use Gcego\Sentinel\Accounts\SecurityHold;
use Gcego\Sentinel\Accounts\SecurityHolds;
use Gcego\Sentinel\Audit\NetworkAddress;
use Gcego\Sentinel\Audit\SecurityEventRecord;
use Gcego\Sentinel\Notifications\SecurityAlertNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

describe('security holds', function (): void {
    it('refuses sign-in and ends sessions of a held account, until it is released', function (): void {
        Notification::fake();
        $user = user();
        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
        $this->assertAuthenticated();

        $hold = app(SecurityHolds::class)->apply($user, 'Suspicious recovery request');

        expect(app(AccountAccess::class)->state($user))->toBe(AccountState::Held);
        app('auth')->forgetGuards();
        $this->get('/dashboard')->assertRedirect();
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertSessionHasErrors(['email' => __('gcego-sentinel::messages.account.unavailable_login')]);
        Notification::assertSentTo($user, SecurityAlertNotification::class, fn (SecurityAlertNotification $notification): bool => $notification->kind === 'security_hold_applied');

        $this->app->forgetScopedInstances();
        app(SecurityHolds::class)->release($user, 'Owner verified by phone');
        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
        expect($hold->fresh()->released_at)->not->toBeNull()->and(SecurityHold::query()->count())->toBe(1);
    });

    it('needs the hold permission, and never lets someone hold or release their own account', function (): void {
        Gate::define('sentinel.holds.manage', static fn ($user): bool => $user->role === 'admin');
        $admin = user(['role' => 'admin']);
        $teacher = user(['role' => 'teacher']);
        $target = user();
        $holds = app(SecurityHolds::class);

        expect(fn () => $holds->apply($target, 'reason', $teacher))->toThrow(AuthorizationException::class)
            ->and(fn () => $holds->apply($admin, 'reason', $admin))->toThrow(AuthorizationException::class)
            ->and(fn () => $holds->apply($target, '   ', $admin))->toThrow(InvalidArgumentException::class);

        $hold = $holds->apply($target, 'Compromised mailbox reported', $admin);
        expect($hold->applied_by)->toBe((string) $admin->id)
            ->and($holds->apply($target, 'again', $admin)->id)->toBe($hold->id);
    });

    it('places and releases holds from the command line', function (): void {
        $user = user();

        $this->artisan('gcego:sentinel-hold', ['user' => $user->email])->expectsOutputToContain('Give a --reason')->assertFailed();
        $this->artisan('gcego:sentinel-hold', ['user' => $user->email, '--reason' => 'Incident 42'])->assertSuccessful();
        expect(SecurityHold::query()->active()->where('user_id', $user->id)->exists())->toBeTrue();

        $this->artisan('gcego:sentinel-release', ['user' => (string) $user->id, '--note' => 'Cleared'])->assertSuccessful();
        expect(SecurityHold::query()->active()->exists())->toBeFalse();
    });

    it('lets an operator reset two-factor authentication for account recovery', function (): void {
        Notification::fake();
        $user = user();
        $this->enableTwoFactor($user);

        $this->artisan('gcego:sentinel-reset-two-factor', ['user' => $user->email, '--force' => true])->assertSuccessful();

        expect($user->fresh()->two_factor_secret)->toBeNull();
        expect(SecurityEventRecord::query()->where('type', 'two_factor.disabled')->first()?->context)->toBe(['administrative' => true]);
        Notification::assertSentTo($user, SecurityAlertNotification::class, fn (SecurityAlertNotification $notification): bool => $notification->kind === 'two_factor_disabled');
    });
});

describe('audit trail', function (): void {
    it('records sign-ins and failures with minimised metadata and no secrets', function (): void {
        $user = user();

        $this->withServerVariables(['REMOTE_ADDR' => '41.202.207.123', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/130.0'])
            ->post('/login', ['email' => $user->email, 'password' => 'Wrong-Secret-123']);
        $this->withServerVariables(['REMOTE_ADDR' => '41.202.207.123'])->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

        $failed = SecurityEventRecord::query()->where('type', 'login.failed')->firstOrFail();
        $succeeded = SecurityEventRecord::query()->where('type', 'login.succeeded')->firstOrFail();

        expect($failed->user_key)->toBe((string) $user->id)
            ->and($failed->outcome)->toBe('failed')
            ->and($failed->ip_address)->toBe('41.202.207.0')
            ->and($failed->user_agent)->toBe('Mozilla/5.0 Firefox/130.0')
            ->and($failed->context)->toBe(['guard' => 'web', 'reason' => 'credentials'])
            ->and($succeeded->context)->toMatchArray(['method' => 'password', 'remember' => false])
            ->and($succeeded->session_reference)->toHaveLength(40);

        $all = json_encode(SecurityEventRecord::query()->get()->toArray());
        expect($all)->not->toContain('Wrong-Secret-123')->not->toContain(PASSWORD)->not->toContain(session()->getId());
    });

    it('shows the user their own recent activity only', function (): void {
        $user = user();
        $other = user();
        $this->post('/login', ['email' => $other->email, 'password' => 'nope-nope-nope']);
        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

        $this->get('/account/security')->assertOk()
            ->assertSee(__('gcego-sentinel::messages.activity.heading'))
            ->assertSee(__('gcego-sentinel::messages.activity.types.login_succeeded'))
            ->assertDontSee(__('gcego-sentinel::messages.activity.types.login_failed'));
    });

    it('minimises addresses as configured', function (): void {
        expect(NetworkAddress::minimise('41.202.207.123', 'truncated'))->toBe('41.202.207.0')
            ->and(NetworkAddress::minimise('2001:db8:abcd:12::1', 'truncated'))->toBe('2001:db8:abcd::')
            ->and(NetworkAddress::minimise('41.202.207.123', 'none'))->toBeNull()
            ->and(NetworkAddress::minimise('41.202.207.123', 'full'))->toBe('41.202.207.123')
            ->and(NetworkAddress::minimise('not-an-ip', 'full'))->toBeNull();
    });

    it('can send the trail to a log channel instead, and can be switched off', function (): void {
        $this->reboot(['gcego-sentinel.audit.sink' => 'log']);
        Log::spy();
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever-password']);

        Log::shouldHaveReceived('channel');
        expect(SecurityEventRecord::query()->count())->toBe(0);

        $this->reboot(['gcego-sentinel.audit.enabled' => false]);
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever-password']);
        expect(SecurityEventRecord::query()->count())->toBe(0);
    });

    it('prunes entries older than the retention period', function (): void {
        SecurityEventRecord::query()->create(['type' => 'login.succeeded', 'outcome' => 'succeeded', 'occurred_at' => now()->subDays(400)]);
        SecurityEventRecord::query()->create(['type' => 'login.succeeded', 'outcome' => 'succeeded', 'occurred_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [SecurityEventRecord::class]])->assertSuccessful();

        expect(SecurityEventRecord::query()->count())->toBe(1);
    });
});

describe('sign-in notices', function (): void {
    it('tells the owner about a sign-in from a new browser when switched on, once per browser', function (): void {
        $this->reboot(['gcego-sentinel.notifications.new_sign_in' => true]);
        Notification::fake();
        $user = user();

        $first = $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
        Notification::assertSentToTimes($user, SecurityAlertNotification::class, 1);
        $cookie = collect($first->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === 'gcego_sentinel_browser');
        expect($cookie)->not->toBeNull()->and($cookie->isHttpOnly())->toBeTrue();

        $this->post('/logout');
        $this->withUnencryptedCookie('gcego_sentinel_browser', $cookie->getValue())->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
        Notification::assertSentToTimes($user, SecurityAlertNotification::class, 1);
    });
});
