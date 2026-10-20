<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\BrowserSessionsRevoked;
use Gcego\Sentinel\Events\EmailChanged;
use Gcego\Sentinel\Events\PasswordChanged;
use Gcego\Sentinel\Livewire\BrowserSessions;
use Gcego\Sentinel\Livewire\ProfileInformationForm;
use Gcego\Sentinel\Livewire\TwoFactorManagement;
use Gcego\Sentinel\Livewire\UpdatePasswordForm;
use Gcego\Sentinel\Notifications\PasswordChangedNotification;
use Gcego\Sentinel\Notifications\SecurityAlertNotification;
use Gcego\Sentinel\Sessions\SessionRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\ViewException;
use Livewire\Livewire;

it('sends guests to sign in', function (): void {
    $this->get('/account/security')->assertRedirect('/login');
});

it('shows the signed-in user\'s own security overview with every available panel', function (): void {
    $user = user(['name' => 'Ngozi Ade', 'email' => 'ngozi@example.test']);

    $response = $this->actingAs($user)->get('/account/security');

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $response->assertOk()
        ->assertSee('Ngozi Ade')->assertSee('ngozi@example.test')
        ->assertSee(__('gcego-sentinel::messages.security.verified'))
        ->assertSee(__('gcego-sentinel::messages.account.states.active'))
        ->assertSee(__('gcego-sentinel::messages.profile.heading'))
        ->assertSee(__('gcego-sentinel::messages.password_update.heading'))
        ->assertSee(__('gcego-sentinel::messages.two_factor.heading'))
        ->assertSee(__('gcego-sentinel::messages.sessions.heading'));

    expect(snapshotOf((string) $response->getContent(), 'gcego-sentinel.update-password'))->not->toBe('');
});

it('hides profile editing and two-factor when their features are off', function (): void {
    $this->reboot(['gcego-sentinel.features.profile' => false, 'gcego-sentinel.features.two_factor' => false]);

    $this->actingAs(user())->get('/account/security')->assertOk()
        ->assertDontSee(__('gcego-sentinel::messages.profile.heading'))
        ->assertDontSee(__('gcego-sentinel::messages.two_factor.heading'));
});

it('updates the name through the profile updater', function (): void {
    $user = user();
    $this->actingAs($user);

    submit(Livewire::test(ProfileInformationForm::class), 'updateProfileInformation', ['state.name' => 'Amina Bello', 'state.email' => $user->email])
        ->assertHasNoErrors()->assertSet('saved', true);

    expect($user->fresh()->name)->toBe('Amina Bello');
});

it('requires a password confirmation to change the email, then re-verifies it and tells the old address', function (): void {
    Notification::fake();
    Event::fake([EmailChanged::class]);
    $user = user();
    $previous = $user->email;
    $this->actingAs($user);
    $component = Livewire::test(ProfileInformationForm::class);

    submit($component, 'updateProfileInformation', ['state.name' => $user->name, 'state.email' => 'new@example.test'])->assertSet('confirmingPassword', true);
    expect($user->fresh()->email)->toBe($user->email);

    submit($component, 'confirmPassword', ['confirmablePassword' => PASSWORD, 'state.email' => 'new@example.test'])->assertSet('verificationSent', true);

    $fresh = $user->fresh();
    expect($fresh->email)->toBe('new@example.test')->and($fresh->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($fresh, VerifyEmail::class);
    Notification::assertSentOnDemand(SecurityAlertNotification::class, fn (SecurityAlertNotification $notification, array $channels, object $notifiable): bool => $notification->kind === 'email_changed' && $notifiable->routes['mail'] === $previous);
    Event::assertDispatched(EmailChanged::class, fn (EmailChanged $event): bool => $event->verificationRequired);
});

it('reports validation errors from the profile updater', function (): void {
    $other = user();
    $this->actingAs(user())->withSession(['auth.password_confirmed_at' => time()]);

    submit(Livewire::test(ProfileInformationForm::class), 'updateProfileInformation', ['state.name' => '', 'state.email' => $other->email])->assertHasErrors(['name', 'email']);
});

it('changes the password with the current one and never keeps it in component state', function (): void {
    Event::fake([PasswordChanged::class]);
    $user = user();
    $this->actingAs($user);
    $component = Livewire::test(UpdatePasswordForm::class);

    submit($component, 'updatePassword', ['state.current_password' => 'wrong', 'state.password' => 'A-new-Password-42', 'state.password_confirmation' => 'A-new-Password-42'])
        ->assertHasErrors('current_password');
    expect(json_encode($component->snapshot))->not->toContain('A-new-Password-42');

    submit($component, 'updatePassword', ['state.current_password' => PASSWORD, 'state.password' => 'A-new-Password-42', 'state.password_confirmation' => 'A-new-Password-42'])
        ->assertHasNoErrors()->assertSet('saved', true)
        ->assertSet('state', ['current_password' => '', 'password' => '', 'password_confirmation' => '']);

    expect(Hash::check('A-new-Password-42', $user->fresh()->password))->toBeTrue()
        ->and(json_encode($component->snapshot))->not->toContain('A-new-Password-42')->not->toContain(PASSWORD);
    Event::assertDispatched(PasswordChanged::class, fn (PasswordChanged $event): bool => $event->userKey === $user->id);
});

it('emails the owner after a password change (unless switched off)', function (): void {
    Notification::fake();
    $user = user();
    $this->actingAs($user);

    submit(Livewire::test(UpdatePasswordForm::class), 'updatePassword', ['state.current_password' => PASSWORD, 'state.password' => 'A-new-Password-42', 'state.password_confirmation' => 'A-new-Password-42']);

    Notification::assertSentTo($user, PasswordChangedNotification::class);

    $this->reboot(['gcego-sentinel.notifications.password_changed' => false]);
    Notification::fake();
    $other = user();
    $this->actingAs($other);
    submit(Livewire::test(UpdatePasswordForm::class), 'updatePassword', ['state.current_password' => PASSWORD, 'state.password' => 'A-new-Password-42', 'state.password_confirmation' => 'A-new-Password-42']);
    Notification::assertNothingSentTo($other);
});

it('refuses a new password that breaks the policy or equals the current one', function (): void {
    $user = user();
    $this->actingAs($user);

    submit(Livewire::test(UpdatePasswordForm::class), 'updatePassword', ['state.current_password' => PASSWORD, 'state.password' => 'too-short', 'state.password_confirmation' => 'too-short'])->assertHasErrors('password');
    submit(Livewire::test(UpdatePasswordForm::class), 'updatePassword', ['state.current_password' => PASSWORD, 'state.password' => PASSWORD, 'state.password_confirmation' => PASSWORD])->assertHasErrors('password');

    expect(Hash::check(PASSWORD, $user->fresh()->password))->toBeTrue();
});

it('explains that sessions cannot be listed without the database driver', function (): void {
    $this->actingAs(user());

    Livewire::test(BrowserSessions::class)->assertSee(__('gcego-sentinel::messages.sessions.unsupported'))->assertSee(__('gcego-sentinel::messages.sessions.logout'));
});

describe('with database sessions', function (): void {
    beforeEach(function (): void {
        $this->reboot(['session.driver' => 'database']);
    });

    function storeSession(int $userId, string $id, string $agent, int $ago = 600): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '41.202.207.1', 'user_agent' => $agent, 'payload' => base64_encode(serialize([])), 'last_activity' => time() - $ago]);
    }

    it('lists the user\'s sessions by digest, never by session id', function (): void {
        $user = user();
        $other = user();
        storeSession($user->id, str_repeat('a', 40), 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0');
        storeSession($user->id, str_repeat('b', 40), 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
        storeSession($other->id, str_repeat('c', 40), 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36');
        $this->actingAs($user);

        $component = Livewire::test(BrowserSessions::class);

        $component->assertSee('Firefox on Linux')->assertSee('Safari on iOS')->assertDontSee('Chrome on Windows')
            ->assertSee(SessionRepository::digest(str_repeat('a', 40)));
        expect($component->html())->not->toContain(str_repeat('a', 40))->not->toContain(str_repeat('c', 40));
    });

    it('signs out other sessions with the password, keeping the current one', function (): void {
        Event::fake([BrowserSessionsRevoked::class]);
        $user = user();
        storeSession($user->id, str_repeat('a', 40), 'Firefox');
        storeSession($user->id, str_repeat('b', 40), 'Chrome');
        $other = user();
        storeSession($other->id, str_repeat('c', 40), 'Edge');
        $this->actingAs($user);
        $component = Livewire::test(BrowserSessions::class);

        submit($component, 'logoutOtherBrowserSessions', ['password' => 'wrong'])->assertHasErrors('password');
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);

        submit($component, 'logoutOtherBrowserSessions', ['password' => PASSWORD])->assertHasNoErrors()->assertSet('signedOut', true)->assertSet('password', '');

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1)
            ->and(json_encode($component->snapshot))->not->toContain(PASSWORD);
        Event::assertDispatched(BrowserSessionsRevoked::class, fn (BrowserSessionsRevoked $event): bool => $event->count === 2 && $event->all);
    });

    it('revokes one session after a password confirmation, and only the user\'s own', function (): void {
        $user = user();
        $other = user();
        storeSession($user->id, str_repeat('a', 40), 'Firefox');
        storeSession($other->id, str_repeat('c', 40), 'Edge');
        $this->actingAs($user);

        Livewire::test(BrowserSessions::class)->call('revokeSession', SessionRepository::digest(str_repeat('a', 40)))->assertForbidden();
        expect(DB::table('sessions')->where('id', str_repeat('a', 40))->exists())->toBeTrue();

        $this->withSession(['auth.password_confirmed_at' => time()]);
        Livewire::test(BrowserSessions::class)->call('revokeSession', SessionRepository::digest(str_repeat('c', 40)));
        expect(DB::table('sessions')->where('id', str_repeat('c', 40))->exists())->toBeTrue();

        Livewire::test(BrowserSessions::class)->call('revokeSession', SessionRepository::digest(str_repeat('a', 40)));
        expect(DB::table('sessions')->where('id', str_repeat('a', 40))->exists())->toBeFalse();
    });
});

it('re-checks the account on every Livewire request', function (): void {
    $user = user();
    $this->actingAs($user);
    $component = Livewire::test(UpdatePasswordForm::class);

    $user->forceFill(['status' => 'suspended'])->save();
    auth()->setUser($user->fresh());

    $component->call('$refresh')->assertForbidden();
});

it('refuses Livewire security components to guests', function (string $component): void {
    try {
        Livewire::test($component);
        $this->fail('The component rendered for a guest.');
    } catch (ViewException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(AuthenticationException::class);
    }
})->with([UpdatePasswordForm::class, ProfileInformationForm::class, BrowserSessions::class, TwoFactorManagement::class]);
