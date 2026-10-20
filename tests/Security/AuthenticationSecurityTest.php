<?php

declare(strict_types=1);

use Gcego\Sentinel\Events\AccountAccessDenied;
use Gcego\Sentinel\Events\LoginRejected;
use Gcego\Sentinel\Redirects\SafeRedirectValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

it('keeps CSRF protection on authentication endpoints and explains expired sessions', function (): void {
    $user = user();
    // The CSRF middleware skips the "testing" environment; act like a real one.
    $this->app['env'] = 'local';

    $response = $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

    $response->assertRedirect('/session-expired');
    $this->assertGuest();
    $this->get('/session-expired')->assertOk()->assertSee(__('gcego-sentinel::messages.expired.heading'))->assertSee('href="http://localhost/login"', false);

    // JSON clients keep the 419.
    $this->postJson('/login', ['email' => $user->email, 'password' => PASSWORD])->assertStatus(419);
});

it('leaves other routes\' errors to the application', function (): void {
    $this->app['env'] = 'local';
    Route::post('/other', static fn (): string => 'ok')->middleware('web');

    $this->post('/other')->assertStatus(419);
});

it('rejects open redirects, dangerous schemes and authentication loops', function (mixed $target, ?string $expected): void {
    $validator = app(SafeRedirectValidator::class);

    expect($validator->internal($target, Request::create('http://localhost/login')))->toBe($expected);
})->with([
    'path' => ['/dashboard?tab=1', '/dashboard?tab=1'],
    'same host' => ['http://localhost/admin/users', '/admin/users'],
    'app url host' => ['http://localhost:80/dashboard', '/dashboard'],
    'other host' => ['https://evil.example/dashboard', null],
    'protocol relative' => ['//evil.example/dashboard', null],
    'backslash' => ['/\\evil.example', null],
    'encoded slashes' => ['/%2F%2Fevil.example', null],
    'javascript' => ['javascript:alert(1)', null],
    'data' => ['data:text/html,<script>alert(1)</script>', null],
    'user info' => ['http://localhost@evil.example/', null],
    'control character' => ["/dashboard\r\nLocation: https://evil.example", null],
    'login loop' => ['/login', null],
    'logout (POST only)' => ['/logout', null],
    'panel login loop' => ['/admin/login', null],
    'two-factor challenge' => ['/two-factor-challenge', null],
    'not a string' => [['/dashboard'], null],
    'unknown page' => ['/somewhere/else', '/somewhere/else'],
]);

it('never writes passwords, codes or tokens into logs or event payloads', function (): void {
    Log::spy();
    Event::fake([AccountAccessDenied::class, LoginRejected::class]);
    $suspended = user(['status' => 'suspended']);
    $student = user(['role' => 'student']);

    $this->post('/login', ['email' => $suspended->email, 'password' => PASSWORD]);
    $this->get('/admin/login');
    $this->post('/login', ['email' => $student->email, 'password' => PASSWORD]);

    foreach ([AccountAccessDenied::class, LoginRejected::class] as $event) {
        Event::assertDispatched($event, function (object $payload): bool {
            $serialized = serialize($payload);

            return ! str_contains($serialized, PASSWORD) && ! str_contains($serialized, 'password') && ! str_contains($serialized, 'session');
        });
    }

    Log::shouldNotHaveReceived('info', [Mockery::on(static fn (mixed $message): bool => is_string($message) && str_contains($message, PASSWORD))]);
});

it('gives the same recovery answer for known and unknown addresses', function (): void {
    $user = user();

    $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'missing@example.test']);

    expect($known->getSession()?->get('status'))->toBe($unknown->getSession()?->get('status'));
});

it('marks every authentication page as private and keeps reset tokens out of referers', function (string $url): void {
    $response = $this->get($url);

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Referrer-Policy'))->toBeIn(['same-origin', 'no-referrer']);
})->with(['/login', '/forgot-password', '/reset-password/token', '/admin/login', '/session-expired', '/signed-out']);

it('regenerates the session on sign-in and invalidates it on sign-out', function (): void {
    $user = user();
    $this->get('/login');
    $guest = session()->getId();
    session()->put('marker', 'before');

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);
    $signedIn = session()->getId();
    expect($signedIn)->not->toBe($guest);

    $this->post('/logout');
    expect(session()->getId())->not->toBe($signedIn)->and(session('marker'))->toBeNull();
});

it('ignores tenant keys and panel ids it does not know', function (): void {
    $user = user();
    $this->withSession(['gcego-sentinel.panel' => 'root', 'gcego-sentinel.tenant' => '../../etc']);

    $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
    $this->post('/logout', ['panel' => 'root', 'tenant' => 'x"><script>'])->assertRedirect('/signed-out');
});

it('does not trust a panel or tenant from the logout form beyond choosing a configured page', function (): void {
    $this->actingAs(user());

    $this->post('/logout', ['panel' => 'institution', 'tenant' => '2'])->assertRedirect('/institution/2/login');
});

it('escapes branding text and rejects unsafe branding URLs', function (): void {
    $this->reboot(['gcego-sentinel.branding' => ['name' => '<script>alert(1)</script>', 'tagline' => '<img src=x onerror=alert(1)>']]);

    $html = (string) $this->get('/login')->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')->not->toContain('<img src=x')->toContain('&lt;script&gt;');
});
