<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Authentication\Identifier;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Passwords\PasswordPolicy;
use Gcego\Sentinel\Passwords\PasswordResets;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * GET /reset-password/{token} and POST /reset-password. An unknown address
 * and a wrong or expired token get the same "invalid link" message. The
 * reset ends every session of the account and returns to the login page
 * the reset started from; it never signs anyone in.
 */
final readonly class NewPasswordController
{
    public function __construct(
        private SentinelManager $sentinel,
        private PasswordResets $resets,
        private PasswordPolicy $policy,
        private AuthenticationRedirectResolver $redirects,
    ) {}

    public function create(Request $request, string $token): Response
    {
        $email = $request->query('email');

        return $this->sentinel->page($request, AuthenticationPage::ResetPassword, [
            'token' => mb_substr($token, 0, 255),
            'email' => is_string($email) ? mb_substr($email, 0, 255) : '',
        ]);
    }

    public function store(Request $request): BaseResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->policy->rules(),
        ]);

        $status = $this->resets->reset([
            'email' => Identifier::normalize($request->string('email')->value(), $this->sentinel->config()->lowercaseIdentifier),
            'password' => $request->string('password')->value(),
            'password_confirmation' => $request->string('password_confirmation')->value(),
            'token' => $request->string('token')->value(),
        ]);

        if ($status !== Password::PASSWORD_RESET) {
            $message = $status === Password::RESET_THROTTLED ? __($status) : __('gcego-sentinel::messages.reset.invalid');

            throw ValidationException::withMessages(['email' => [is_string($message) ? $message : '']]);
        }

        $message = __('gcego-sentinel::messages.reset.done');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message], 200);
        }

        return Redirect::to($this->redirects->afterPasswordReset($request), $request)->with('status', $message);
    }
}
