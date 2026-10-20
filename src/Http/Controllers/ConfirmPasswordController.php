<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Passwords\PasswordPolicy;
use Gcego\Sentinel\Redirects\SafeRedirectValidator;
use Gcego\Sentinel\Security\StepUp;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Password confirmation (GET/POST /user/confirm-password) for Laravel's
 * "password.confirm" middleware and Sentinel's step-up checks, with the
 * same session timestamp ("auth.password_confirmed_at"). Throttled.
 */
final readonly class ConfirmPasswordController
{
    public function __construct(
        private SentinelManager $sentinel,
        private StepUp $stepUp,
        private SafeRedirectValidator $validator,
        private AuthenticationRedirectResolver $redirects,
    ) {}

    public function show(Request $request): Response
    {
        return $this->sentinel->page($request, AuthenticationPage::ConfirmPassword);
    }

    public function store(Request $request): BaseResponse
    {
        $request->validate(['password' => PasswordPolicy::currentRules()]);
        $user = $request->user($this->sentinel->guardName());

        if ($user === null || ! $this->sentinel->provider()->validateCredentials($user, ['password' => $request->string('password')->value()])) {
            throw ValidationException::withMessages(['password' => [__('gcego-sentinel::messages.confirm.failed')]]);
        }

        $this->stepUp->confirm($request->session());

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        $intended = $this->validator->internal($request->session()->pull('url.intended'), $request);

        return new RedirectResponse($intended ?? $this->redirects->home());
    }

    public function status(Request $request): JsonResponse
    {
        return new JsonResponse(['confirmed' => $this->stepUp->confirmed($request->session())]);
    }
}
