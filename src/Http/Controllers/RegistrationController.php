<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\Events\RegistrationCompleted;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\Routes;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * GET/POST /register, behind sentinel.registration (the RegistrationPolicy
 * and the terms checkbox) and a throttle. Only the built-in and configured
 * fields reach the CreatesAccounts implementation, inside a transaction.
 * The new account is signed in without any role, and is only sent into a
 * panel that gcego/security lets it enter.
 */
final readonly class RegistrationController
{
    public function __construct(
        private SentinelManager $sentinel,
        private SentinelConfig $config,
        private CreatesAccounts $creator,
        private AuthenticationPanels $panels,
        private AuthenticationRedirectResolver $redirects,
        private SessionSecurity $sessions,
        private Dispatcher $events,
        private UrlGenerator $urls,
    ) {}

    public function create(Request $request): Response
    {
        return $this->sentinel->page($request, AuthenticationPage::Register);
    }

    public function store(Request $request): BaseResponse
    {
        $fields = ['name', 'email', 'password', 'password_confirmation', ...array_map(static fn ($field): string => $field->name, $this->config->registrationFields)];
        $input = [];

        foreach ($fields as $field) {
            $value = $request->input($field);
            $input[$field] = is_scalar($value) ? (string) $value : '';
        }

        $id = $request->session()->get(SessionKeys::Panel);
        $panel = is_string($id) ? $this->panels->find($id) : null;
        $user = DB::transaction(fn () => $this->creator->create($input, $panel));

        $this->events->dispatch(new Registered($user));
        $this->events->dispatch(new RegistrationCompleted(UserKey::of($user), $this->sentinel->guardName(), $panel?->id));

        $this->sentinel->guard()->login($user);
        $request->session()->regenerate();
        $this->sessions->start($request->session(), $user, false);

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        if ($this->config->emailVerification && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() && Routes::has(SentinelRoutes::VerificationNotice)) {
            return new RedirectResponse($this->urls->route(SentinelRoutes::VerificationNotice, [], false));
        }

        return new RedirectResponse((string) $this->redirects->afterRegistration($request, $user)->url);
    }
}
