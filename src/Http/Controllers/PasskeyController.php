<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Authentication\LoginPipeline;
use Gcego\Sentinel\Events\LoginFailed;
use Gcego\Sentinel\Passkeys\PasskeyAuthentication;
use Gcego\Sentinel\Security\StepUp;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The JSON endpoints of the passkey ceremonies (Sentinel's script calls
 * navigator.credentials between them):
 *
 *  - GET  /passkeys/login/options, POST /passkeys/login (guests): a verified
 *    assertion goes through the LoginPipeline like any other sign-in;
 *  - GET  /user/passkeys/options, POST /user/passkeys (signed in, password
 *    confirmed recently): registers a passkey.
 *
 * Removing a passkey happens on the account security page (Livewire).
 */
final readonly class PasskeyController
{
    public function __construct(
        private SentinelManager $sentinel,
        private SentinelConfig $config,
        private PasskeyAuthentication $passkeys,
        private LoginPipeline $pipeline,
        private StepUp $stepUp,
        private TwoFactorPolicy $twoFactor,
        private Dispatcher $events,
    ) {}

    public function loginOptions(Request $request): JsonResponse
    {
        return new JsonResponse(['options' => $this->passkeys->loginOptions($request->session())]);
    }

    public function login(Request $request): Response
    {
        $request->validate(self::credentialRules() + ['remember' => ['nullable', 'boolean']]);

        try {
            $passkey = $this->passkeys->verifyLogin($request->session(), (array) $request->input('credential'));
        } catch (ValidationException $exception) {
            $this->events->dispatch(new LoginFailed(null, $this->sentinel->guardName(), 'passkey'));

            throw $exception;
        }

        $user = $passkey->user;

        if (! $user instanceof Authenticatable) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.not_recognized')]]);
        }

        return $this->pipeline->afterPasskey($request, $user, $this->config->remember && $request->boolean('remember'));
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        $this->ensureConfirmed($request);

        return new JsonResponse(['options' => $this->passkeys->registrationOptions($request->session(), $this->user($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureConfirmed($request);
        $request->validate(self::credentialRules() + ['name' => ['required', 'string', 'max:60']]);
        $passkey = $this->passkeys->register($request->session(), $this->user($request), trim($request->string('name')->value()), (array) $request->input('credential'));

        // A verified passkey that counts as a second factor proves it for this session, like confirming an authenticator.
        if ($this->config->passkeysSatisfyTwoFactor) {
            $this->twoFactor->markSession($request->session(), true);
        }

        return new JsonResponse(['id' => $passkey->getKey(), 'name' => $passkey->name], 201);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function credentialRules(): array
    {
        return [
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string', 'max:1024'],
            'credential.rawId' => ['required', 'string', 'max:1024'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ];
    }

    private function ensureConfirmed(Request $request): void
    {
        if (! $this->stepUp->confirmed($request->session())) {
            abort(new JsonResponse(['message' => __('gcego-sentinel::messages.confirm.required')], 423));
        }
    }

    private function user(Request $request): Authenticatable
    {
        return $request->user($this->sentinel->guardName()) ?? abort(401);
    }
}
