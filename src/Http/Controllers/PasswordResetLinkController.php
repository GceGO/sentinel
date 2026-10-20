<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Authentication\Identifier;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Passwords\PasswordResets;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * GET/POST /forgot-password. The answer is the same whether or not the
 * address has an account (and whether a link was sent recently), so the
 * form cannot be used to find out who is registered. Throttled per address
 * and per client.
 */
final readonly class PasswordResetLinkController
{
    public function __construct(private SentinelManager $sentinel, private PasswordResets $resets, private UrlGenerator $urls) {}

    public function create(Request $request): Response
    {
        return $this->sentinel->page($request, AuthenticationPage::ForgotPassword);
    }

    public function store(Request $request): BaseResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $status = $this->resets->sendLink(Identifier::normalize($request->string('email')->value(), $this->sentinel->config()->lowercaseIdentifier));

        if (! in_array($status, PasswordResets::silentStatuses(), true)) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        $message = __('gcego-sentinel::messages.forgot.sent');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message], 200);
        }

        return Redirect::to($this->urls->previous(), $request)->with('status', $message);
    }
}
