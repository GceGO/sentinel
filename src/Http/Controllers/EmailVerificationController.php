<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Events\EmailVerified;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Email verification with Laravel's signed, expiring links
 * (auth.verification.expire):
 *
 *  - the link only verifies the signed-in account it was made for (the id
 *    must match, and the hash must match the account's current address, so
 *    a link sent before an email change is dead);
 *  - verifying twice is harmless; resending is throttled.
 */
final readonly class EmailVerificationController
{
    public function __construct(
        private SentinelManager $sentinel,
        private AuthenticationRedirectResolver $redirects,
        private Dispatcher $events,
    ) {}

    public function notice(Request $request): Response
    {
        $user = $request->user($this->sentinel->guardName());

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return new RedirectResponse($this->redirects->home());
        }

        return $this->sentinel->page($request, AuthenticationPage::VerifyEmail);
    }

    public function verify(Request $request, string $id, string $hash): Response
    {
        $user = $request->user($this->sentinel->guardName());

        if (! $user instanceof MustVerifyEmail || ! hash_equals((string) UserKey::of($user), $id) || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AccessDeniedHttpException(__('gcego-sentinel::messages.verify.invalid'));
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            $this->events->dispatch(new Verified($user));
            $this->events->dispatch(new EmailVerified(UserKey::of($user)));
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return new RedirectResponse((string) $this->redirects->afterVerification($request, $user)->url);
    }

    public function send(Request $request): Response
    {
        $user = $request->user($this->sentinel->guardName());

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return $request->wantsJson() ? new JsonResponse('', 204) : new RedirectResponse($this->redirects->home());
        }

        $user->sendEmailVerificationNotification();

        return $request->wantsJson() ? new JsonResponse('', 202) : back()->with('status', 'verification-link-sent');
    }
}
