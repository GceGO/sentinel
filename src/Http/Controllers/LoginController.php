<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Authentication\CredentialAuthenticator;
use Gcego\Sentinel\Authentication\LoginPipeline;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Passwords\PasswordPolicy;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * GET /login (the site's sign-in page; panels have their own) and
 * POST /login (identifier + password, then LoginPipeline).
 */
final readonly class LoginController
{
    public function __construct(
        private SentinelManager $sentinel,
        private SentinelConfig $config,
        private CredentialAuthenticator $credentials,
        private LoginPipeline $pipeline,
    ) {}

    public function create(Request $request): Response
    {
        // The site's own login page starts a sign-in outside any panel.
        $request->session()->forget([SessionKeys::Panel, SessionKeys::Tenant]);

        return $this->sentinel->page($request, AuthenticationPage::Login);
    }

    public function store(Request $request): BaseResponse
    {
        $field = $this->config->identifier;
        $request->validate([
            $field => ['required', 'string', 'max:255'],
            'password' => PasswordPolicy::currentRules(),
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = $this->config->remember && $request->boolean('remember');
        $user = $this->credentials->authenticate($request->string($field)->value(), $request->string('password')->value(), $remember, $request->ip());

        return $this->pipeline->afterPassword($request, $user, $remember);
    }
}
