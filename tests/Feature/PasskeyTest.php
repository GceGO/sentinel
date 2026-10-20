<?php

declare(strict_types=1);

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Events\LoginFailed;
use Gcego\Sentinel\Events\LoginSucceeded;
use Gcego\Sentinel\Events\PasskeyRemoved;
use Gcego\Sentinel\Livewire\PasskeyManagement;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * A structurally valid WebAuthn assertion (it parses), with a made-up signature.
 *
 * @return array<string, mixed>
 */
function forgedAssertion(string $challenge = 'not-the-challenge'): array
{
    $challenge = Base64UrlSafe::encodeUnpadded($challenge);
    $id = Base64UrlSafe::encodeUnpadded(random_bytes(16));

    return [
        'id' => $id,
        'rawId' => $id,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded((string) json_encode(['type' => 'webauthn.get', 'challenge' => $challenge, 'origin' => 'http://localhost'])),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded(hash('sha256', 'localhost', true).chr(0x05).pack('N', 1)),
            'signature' => Base64UrlSafe::encodeUnpadded('not-a-signature'),
            'userHandle' => Base64UrlSafe::encodeUnpadded('handle'),
        ],
    ];
}

function passkeyFor(User $user): Passkey
{
    return $user->passkeys()->create(['name' => 'Laptop', 'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(16)), 'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000']]);
}

/**
 * Stand in for the WebAuthn verification (done by web-auth/webauthn-lib) to exercise what happens after it.
 */
function trustPasskey(Passkey $passkey): void
{
    app()->instance(VerifyPasskey::class, new class($passkey) extends VerifyPasskey
    {
        public function __construct(private Passkey $passkey) {}

        public function __invoke(PublicKeyCredential $credential, PublicKeyCredentialRequestOptions $options, ?PasskeyUser $user = null): Passkey
        {
            return $this->passkey->fresh();
        }
    });
}

it('offers passkey sign-in on the login page only when passkeys are on', function (): void {
    $this->get('/login')->assertSee('data-sentinel-passkey-login', false)->assertSee('data-options-url="http://localhost/passkeys/login/options"', false);

    $this->reboot(['gcego-sentinel.features.passkeys' => false]);
    $this->get('/login')->assertDontSee('data-sentinel-passkey-login', false);
    $this->getJson('/passkeys/login/options')->assertNotFound();
});

it('issues single-use login challenges that require user verification', function (): void {
    $options = $this->getJson('/passkeys/login/options')->assertOk()->json('options');

    expect($options['challenge'])->toBeString()->not->toBe('')
        ->and($options['rpId'])->toBe('localhost')
        ->and($options['userVerification'])->toBe('required')
        ->and(session(SessionKeys::PasskeyOptions)['ceremony'])->toBe('login');

    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertStatus(422);
    // The challenge was consumed by the failed attempt: replaying it fails before verification.
    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertStatus(422)->assertJsonPath('errors.passkey.0', __('gcego-sentinel::messages.passkeys.expired'));
});

it('rejects forged or unknown assertions with the real WebAuthn verification', function (): void {
    Event::fake([LoginFailed::class]);
    $user = user();
    passkeyFor($user);

    $this->getJson('/passkeys/login/options');
    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertStatus(422)->assertJsonPath('errors.passkey.0', __('gcego-sentinel::messages.passkeys.not_recognized'));

    $this->assertGuest();
    Event::assertDispatched(LoginFailed::class, fn (LoginFailed $event): bool => $event->reason === 'passkey');
});

it('expires login challenges', function (): void {
    $this->getJson('/passkeys/login/options');
    $this->travel(5)->minutes();

    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertStatus(422)->assertJsonPath('errors.passkey.0', __('gcego-sentinel::messages.passkeys.expired'));
});

it('signs in through the same pipeline after a verified passkey: multi-factor, account state and panels enforced', function (): void {
    Event::fake([LoginSucceeded::class]);
    $user = user(['role' => 'admin']);
    trustPasskey(passkeyFor($user));

    $this->get('/admin/login');
    $this->getJson('/passkeys/login/options');
    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertOk()->assertJson(['redirect' => '/admin']);

    $this->assertAuthenticatedAs($user);
    expect(session(SessionKeys::Assurance))->toBe('multi');
    Event::assertDispatched(LoginSucceeded::class, fn (LoginSucceeded $event): bool => $event->method === 'passkey');
});

it('refuses a verified passkey of a suspended account or an account outside the panel', function (): void {
    $suspended = user(['status' => 'suspended']);
    trustPasskey(passkeyFor($suspended));
    $this->getJson('/passkeys/login/options');
    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertStatus(403);
    $this->assertGuest();

    $student = user(['role' => 'student']);
    trustPasskey(passkeyFor($student));
    $this->get('/admin/login');
    $this->getJson('/passkeys/login/options');
    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertStatus(403);
    $this->assertGuest();
});

it('still asks for the authenticator code when passkeys do not count as two-factor', function (): void {
    $this->reboot(['gcego-sentinel.passkeys.satisfies_two_factor' => false]);
    $user = user();
    $this->enableTwoFactor($user);
    trustPasskey(passkeyFor($user->fresh()));

    $this->getJson('/passkeys/login/options');
    $this->postJson('/passkeys/login', ['credential' => forgedAssertion()])->assertOk()->assertJson(['two_factor' => true]);
    $this->assertGuest();
});

it('requires a password-only sign-in to use the passkey when it is the required second factor', function (): void {
    $this->reboot(['gcego-sentinel.two_factor.required_permissions' => ['security.manage']]);
    $admin = user(['role' => 'admin']);
    passkeyFor($admin);

    $this->post('/login', ['email' => $admin->email, 'password' => PASSWORD])->assertSessionHasErrors(['email' => __('gcego-sentinel::messages.passkeys.required')]);
    $this->assertGuest();
});

it('needs a recent password confirmation to register a passkey, and caps how many', function (): void {
    $this->reboot(['gcego-sentinel.passkeys.max_per_user' => 1]);
    $user = user();
    $this->actingAs($user);

    $this->getJson('/user/passkeys/options')->assertStatus(423);
    $this->postJson('/user/passkeys', ['name' => 'Laptop', 'credential' => forgedAssertion()])->assertStatus(423);

    $options = $this->withConfirmedPassword()->getJson('/user/passkeys/options')->assertOk()->json('options');
    expect($options['authenticatorSelection']['userVerification'])->toBe('required')
        ->and($options['authenticatorSelection']['residentKey'])->toBe('required')
        ->and($options['user']['name'])->toBe($user->email);

    // A credential that is not a valid attestation for these options is refused.
    $this->postJson('/user/passkeys', ['name' => 'Laptop', 'credential' => forgedAssertion()])->assertStatus(422);
    expect($user->passkeys()->count())->toBe(0);

    passkeyFor($user);
    $this->getJson('/user/passkeys/options')->assertStatus(422)->assertJsonPath('errors.passkey.0', __('gcego-sentinel::messages.passkeys.limit', ['max' => 1]));
});

it('lists and removes only the user\'s own passkeys, after a password confirmation', function (): void {
    Event::fake([PasskeyRemoved::class]);
    $user = user();
    $other = user();
    $mine = passkeyFor($user);
    $theirs = passkeyFor($other);
    $this->actingAs($user);

    Livewire::test(PasskeyManagement::class)->assertSee('Laptop')->call('removePasskey', (string) $mine->id)->assertForbidden();

    $this->withConfirmedPassword();
    Livewire::test(PasskeyManagement::class)->call('removePasskey', (string) $theirs->id);
    expect(Passkey::query()->whereKey($theirs->id)->exists())->toBeTrue();

    Livewire::test(PasskeyManagement::class)->call('removePasskey', (string) $mine->id)->assertSee(__('gcego-sentinel::messages.passkeys.removed'));
    expect(Passkey::query()->whereKey($mine->id)->exists())->toBeFalse();
    Event::assertDispatched(PasskeyRemoved::class, fn (PasskeyRemoved $event): bool => $event->userKey === $user->id);
});

it('keeps the last passkey when it is the only required second factor', function (): void {
    $this->reboot(['gcego-sentinel.two_factor.required_permissions' => ['security.manage']]);
    $admin = user(['role' => 'admin']);
    $only = passkeyFor($admin);
    $this->actingAs($admin)->withConfirmedPassword();

    Livewire::test(PasskeyManagement::class)->call('removePasskey', (string) $only->id)->assertHasErrors('passkey');

    expect(Passkey::query()->whereKey($only->id)->exists())->toBeTrue();
});

it('hands registration to the browser only after the password is confirmed', function (): void {
    $this->actingAs(user());

    Livewire::test(PasskeyManagement::class)->set('name', 'Phone')->call('prepareRegistration')->assertForbidden();

    $this->withConfirmedPassword();
    Livewire::test(PasskeyManagement::class)->set('name', 'Phone')->call('prepareRegistration')
        ->assertDispatched('gcego-sentinel-passkey-register', name: 'Phone')
        ->assertSet('registering', true);
});
