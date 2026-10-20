<?php

declare(strict_types=1);

namespace Gcego\Sentinel;

use Gcego\Sentinel\Features\Feature;

/**
 * The pages Sentinel renders. The first eight are switchable in
 * gcego-sentinel.pages and replaceable in gcego-sentinel.views.
 */
enum AuthenticationPage: string
{
    case Login = 'login';
    case Register = 'register';
    case ForgotPassword = 'forgot_password';
    case ResetPassword = 'reset_password';
    case VerifyEmail = 'verify_email';
    case ConfirmPassword = 'confirm_password';
    case TwoFactorChallenge = 'two_factor_challenge';
    case AccountSecurity = 'account_security';
    case AccountUnavailable = 'account_unavailable';
    case AccountRecovery = 'account_recovery';
    case SessionExpired = 'session_expired';
    case LoggedOut = 'logged_out';

    /**
     * Sentinel's own view for the page.
     */
    public function view(): string
    {
        return SentinelServiceProvider::Views.'::pages.'.str_replace('_', '-', $this->value);
    }

    /**
     * The feature the page presents, or null for informational pages.
     */
    public function feature(): ?Feature
    {
        return match ($this) {
            self::Login => Feature::Login,
            self::Register => Feature::Registration,
            self::ForgotPassword, self::ResetPassword => Feature::PasswordReset,
            self::VerifyEmail => Feature::EmailVerification,
            self::ConfirmPassword => Feature::PasswordConfirmation,
            self::TwoFactorChallenge => Feature::TwoFactorAuthentication,
            self::AccountSecurity => Feature::AccountSecurity,
            default => null,
        };
    }

    /**
     * Whether gcego-sentinel.pages can switch this page off.
     */
    public function isSwitchable(): bool
    {
        return ! in_array($this, [self::AccountUnavailable, self::AccountRecovery, self::SessionExpired, self::LoggedOut], true);
    }

    public function title(): string
    {
        return __(SentinelServiceProvider::Views.'::messages.pages.'.$this->value.'.title');
    }
}
