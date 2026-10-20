<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Features;

/**
 * An authentication capability Sentinel can present. Each one is only
 * available when its backend is: the feature switch, a session driver,
 * the user model, a configured route (see ConfiguredFeatureResolver).
 */
enum Feature: string
{
    case Login = 'login';
    case Registration = 'registration';
    case PasswordReset = 'password_reset';
    case EmailVerification = 'email_verification';
    case PasswordConfirmation = 'password_confirmation';
    case TwoFactorAuthentication = 'two_factor_authentication';
    case Passkeys = 'passkeys';
    case ProfileInformation = 'profile_information';
    case PasswordUpdate = 'password_update';
    case BrowserSessions = 'browser_sessions';
    case AccountSecurity = 'account_security';
}
