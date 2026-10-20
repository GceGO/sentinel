<?php

declare(strict_types=1);

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Events\LoginRejected;
use Gcego\Sentinel\Livewire\TwoFactorManagement;
use Gcego\Sentinel\Tests\Fixtures\TenantResolver;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

it('gives each panel its own branded login page posting to the shared endpoint', function (string $path, string $title): void {
    $response = $this->get($path);

    $response->assertOk()
        ->assertSee($title)
        ->assertSee(__('gcego-sentinel::messages.login.panel_description', ['panel' => $title]))
        ->assertSee('action="http://localhost/login"', false);
    expect(session(SessionKeys::Panel))->not->toBeNull();
})->with([
    'admin' => ['/admin/login', 'Administration'],
    'teacher' => ['/teacher/login', 'Teachers'],
]);

it('uses the panel\'s layout', function (): void {
    expect((string) $this->get('/teacher/login')->getContent())->toContain('data-sentinel-layout="centered"')->not->toContain('data-sentinel-layout="split"');
    expect((string) $this->get('/admin/login')->getContent())->toContain('data-sentinel-layout="split"');
});

it('sends authorized members to their panel home', function (string $panel, string $role, string $home): void {
    $user = user(['role' => $role]);
    $this->get("/{$panel}/login");

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect($home);

    $this->assertAuthenticatedAs($user);
})->with([
    'admin' => ['admin', 'admin', '/admin'],
    'teacher' => ['teacher', 'teacher', '/teacher'],
]);

it('signs a user out again when they may not enter the panel they signed in to', function (): void {
    Event::fake([LoginRejected::class]);
    $student = user(['role' => 'student']);
    $this->get('/admin/login');

    $this->post('/login', ['email' => $student->email, 'password' => PASSWORD])
        ->assertRedirect('/admin/login')
        ->assertSessionHasErrors(['email' => __('gcego-sentinel::messages.panel.denied', ['panel' => 'Administration'])]);

    $this->assertGuest();
    Event::assertDispatched(LoginRejected::class, fn (LoginRejected $event): bool => $event->userKey === $student->id && $event->panel === 'admin' && $event->reason === 'panel_denied');
});

it('checks the panel of the intended URL too, wherever the sign-in started', function (): void {
    $student = user(['role' => 'student']);

    $this->get('/admin/users')->assertRedirect('/login');
    $this->get('/login');
    $this->post('/login', ['email' => $student->email, 'password' => PASSWORD])->assertRedirect('/admin/login');
    $this->assertGuest();

    $admin = user(['role' => 'admin']);
    $this->get('/admin/users')->assertRedirect('/login');
    $this->post('/login', ['email' => $admin->email, 'password' => PASSWORD])->assertRedirect('/admin/users');
});

it('forgets the panel when the site\'s own login page is used', function (): void {
    $student = user(['role' => 'student']);
    $this->get('/admin/login');
    $this->get('/login');

    expect(session(SessionKeys::Panel))->toBeNull();
    $this->post('/login', ['email' => $student->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
});

it('shows the institution on a tenant panel\'s login page and 404s unknown tenants', function (): void {
    $this->get('/institution/1/login')->assertOk()->assertSee('Lycée Bilingue de Buea');
    $this->get('/institution/9/login')->assertNotFound();
    $this->get('/institution/'.str_repeat('a', 101).'/login')->assertNotFound();
});

it('lets institution members in after verifying the membership through gcego/security', function (): void {
    $member = member(user(), 1);
    $this->get('/institution/1/login');

    $this->post('/login', ['email' => $member->email, 'password' => PASSWORD])->assertRedirect('/institution/1');

    expect(session('gcego.tenant'))->toBe('1');
});

it('rejects users without a membership, or without the tenant permission', function (): void {
    $outsider = member(user(), 2);
    $this->get('/institution/1/login');
    $this->post('/login', ['email' => $outsider->email, 'password' => PASSWORD])->assertRedirect('/institution/1/login');
    $this->assertGuest();

    $viewer = member(user(), 1, ['institution.view']);
    $this->get('/institution/1/login');
    $this->post('/login', ['email' => $viewer->email, 'password' => PASSWORD])->assertRedirect('/institution/1/login');
    $this->assertGuest();
});

it('never redirects across tenants a user is not a member of', function (): void {
    $member = member(user(), 1);
    $this->get('/institution/2/classes')->assertRedirect('/login');
    $this->get('/institution/1/login');

    $this->post('/login', ['email' => $member->email, 'password' => PASSWORD])->assertRedirect('/institution/2/login');

    $this->assertGuest();
});

it('does not grant global roles access to a tenant panel', function (): void {
    $admin = user(['role' => 'admin']);
    $this->get('/institution/1/login');

    $this->post('/login', ['email' => $admin->email, 'password' => PASSWORD])->assertRedirect('/institution/1/login');

    $this->assertGuest();
});

it('looks the tenant up once per page and not at all on other pages', function (): void {
    $this->get('/institution/1/login');
    expect(TenantResolver::$lookups)->toBeLessThanOrEqual(2);

    TenantResolver::$lookups = 0;
    $this->get('/admin/login');
    expect(TenantResolver::$lookups)->toBe(0);
});

it('requires two-factor enrollment before entering a panel that demands it', function (): void {
    $this->reboot(['gcego-sentinel.panels.admin' => ['path' => 'admin', 'title' => 'Administration', 'permission' => 'panels.admin', 'two_factor' => true]]);
    $admin = user(['role' => 'admin']);
    $this->get('/admin/login');

    $this->post('/login', ['email' => $admin->email, 'password' => PASSWORD])->assertRedirect('/account/security');

    expect(session(SessionKeys::Continue))->toBe('/admin');
    $this->get('/admin/secure')->assertRedirect('/account/security');
    $this->get('/account/security')->assertOk()->assertSee(__('gcego-sentinel::messages.two_factor.required'));

    // Enrolling in this session proves the second factor, so the session may continue.
    $this->withSession(['auth.password_confirmed_at' => time()]);
    $component = Livewire::test(TwoFactorManagement::class)->call('enableTwoFactorAuthentication');
    submit($component, 'confirmTwoFactorAuthentication', ['code' => $this->twoFactorCode($admin->fresh())])->assertHasNoErrors();
    $this->get('/admin/secure')->assertOk()->assertSee('Admin secure');
});

it('signs a password-only session out of a panel that requires two-factor authentication', function (): void {
    $this->reboot(['gcego-sentinel.panels.admin' => ['path' => 'admin', 'title' => 'Administration', 'permission' => 'panels.admin', 'two_factor' => true]]);
    $admin = user(['role' => 'admin']);
    $this->enableTwoFactor($admin);

    // A session that never proved the second factor (signed in before the requirement, say).
    $this->actingAs($admin->fresh())->withSession(['gcego-sentinel.assurance' => 'single']);
    $this->get('/admin/secure')->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('never gives a remember-me cookie to users who must use two-factor authentication', function (): void {
    $this->reboot(['gcego-sentinel.panels.admin' => ['path' => 'admin', 'title' => 'Administration', 'permission' => 'panels.admin', 'two_factor' => true]]);
    $admin = user(['role' => 'admin']);
    $this->enableTwoFactor($admin);
    $this->get('/admin/login');

    $this->post('/login', ['email' => $admin->email, 'password' => PASSWORD, 'remember' => '1']);
    $response = $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($admin)])->assertRedirect('/admin');

    expect(collect($response->headers->getCookies())->map->getName()->filter(fn (string $name): bool => str_starts_with($name, 'remember_')))->toBeEmpty()
        ->and($admin->fresh()->remember_token)->toBeNull();
});
