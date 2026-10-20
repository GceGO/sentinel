<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Authentication\LoginPipeline;
use Gcego\Sentinel\Authentication\PendingLogin;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Events\TwoFactorChallengeFailed;
use Gcego\Sentinel\MultiFactor\TwoFactorAuthentication;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\Redirect;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The second step of a password sign-in (GET/POST /two-factor-challenge).
 * Only a session holding a PendingLogin gets here; a wrong, replayed or
 * already-used code counts against it, and after
 * two_factor.challenge_attempts failures (or challenge_timeout seconds)
 * the password must be entered again. Throttled per pending account.
 */
final readonly class TwoFactorChallengeController
{
    public function __construct(
        private SentinelManager $sentinel,
        private SentinelConfig $config,
        private TwoFactorAuthentication $twoFactor,
        private LoginPipeline $pipeline,
        private AuthenticationRedirectResolver $redirects,
        private Dispatcher $events,
    ) {}

    public function create(Request $request): Response
    {
        if ($this->pending($request) === null) {
            return $this->restart($request);
        }

        return $this->sentinel->page($request, AuthenticationPage::TwoFactorChallenge, ['recovery' => $request->boolean('recovery')]);
    }

    public function store(Request $request): Response
    {
        $pending = PendingLogin::from($request->session());
        $user = $pending === null ? null : $this->user($pending);

        if ($pending === null || $user === null || ! $this->twoFactor->enrolled($user)) {
            PendingLogin::forget($request->session());

            return $this->restart($request);
        }

        $recovery = $request->filled('recovery_code');
        $request->validate($recovery
            ? ['recovery_code' => ['required', 'string', 'max:64']]
            : ['code' => ['required', 'string', 'max:16']]);

        $verified = $recovery
            ? $this->twoFactor->redeemRecoveryCode($user, $request->string('recovery_code')->value())
            : $this->twoFactor->verify($user, $request->string('code')->value());

        if (! $verified) {
            $pending = $pending->failed($request->session());
            $exhausted = $pending->attempts >= $this->config->challengeAttempts;
            $this->events->dispatch(new TwoFactorChallengeFailed($pending->userKey, $recovery ? 'recovery_code' : 'code', $exhausted));

            if ($exhausted) {
                PendingLogin::forget($request->session());

                return $this->restart($request, __('gcego-sentinel::messages.challenge.exhausted'));
            }

            $field = $recovery ? 'recovery_code' : 'code';

            throw ValidationException::withMessages([$field => [__($recovery ? 'gcego-sentinel::messages.challenge.invalid_recovery' : 'gcego-sentinel::messages.challenge.invalid_code')]]);
        }

        return $this->pipeline->complete($request, $user, $pending->remember, $recovery ? 'recovery_code' : 'two_factor', true);
    }

    private function pending(Request $request): ?PendingLogin
    {
        return PendingLogin::from($request->session());
    }

    private function user(PendingLogin $pending): ?Authenticatable
    {
        return $this->sentinel->provider()->retrieveById($pending->userKey);
    }

    private function restart(Request $request, ?string $message = null): Response
    {
        $message ??= __('gcego-sentinel::messages.challenge.expired');

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], 419);
        }

        return Redirect::to($this->redirects->login($request), $request)->withErrors([$this->config->identifier => $message]);
    }
}
