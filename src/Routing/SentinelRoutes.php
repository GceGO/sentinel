<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Routing;

use Gcego\Sentinel\Panels\AuthenticationPanel;

/**
 * Names of the routes Sentinel registers. The authentication endpoints keep
 * the names (and URLs) Laravel's middleware and the GCEGO packages expect
 * ("login", "password.confirm", "verification.notice"...).
 */
final class SentinelRoutes
{
    public const string Login = 'login';

    public const string LoginStore = 'login.store';

    public const string Logout = 'logout';

    public const string Register = 'register';

    public const string RegisterStore = 'register.store';

    public const string PasswordRequest = 'password.request';

    public const string PasswordEmail = 'password.email';

    public const string PasswordReset = 'password.reset';

    public const string PasswordUpdate = 'password.update';

    public const string PasswordConfirm = 'password.confirm';

    public const string PasswordConfirmStore = 'password.confirm.store';

    public const string PasswordConfirmation = 'password.confirmation';

    public const string TwoFactorChallenge = 'two-factor.login';

    public const string TwoFactorChallengeStore = 'two-factor.login.store';

    public const string VerificationNotice = 'verification.notice';

    public const string VerificationVerify = 'verification.verify';

    public const string VerificationSend = 'verification.send';

    public const string PasskeyLoginOptions = 'passkey.login-options';

    public const string PasskeyLogin = 'passkey.login';

    public const string PasskeyRegistrationOptions = 'passkey.registration-options';

    public const string PasskeyStore = 'passkey.store';

    public const string AccountSecurity = 'sentinel.account.security';

    public const string AccountUnavailable = 'sentinel.account.unavailable';

    public const string AccountRecovery = 'sentinel.account.recovery';

    public const string SessionExpired = 'sentinel.session.expired';

    public const string LoggedOut = 'sentinel.logged-out';

    public const string Assets = 'sentinel.assets';

    public static function panelLogin(AuthenticationPanel $panel): string
    {
        return 'sentinel.panels.'.$panel->id.'.login';
    }

    public static function panelRegister(AuthenticationPanel $panel): string
    {
        return 'sentinel.panels.'.$panel->id.'.register';
    }
}
