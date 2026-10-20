<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Context;

/**
 * Session keys Sentinel uses. None of them is trusted: the panel id is
 * looked up in configuration and the tenant key is verified by gcego/security
 * before anything depends on it.
 */
final class SessionKeys
{
    /** The panel whose login or register page the visitor opened. */
    public const string Panel = 'gcego-sentinel.panel';

    /** The tenant key of that page (tenant panels). */
    public const string Tenant = 'gcego-sentinel.tenant';

    /** Where a user sent to enroll in two-factor authentication was going. */
    public const string Continue = 'gcego-sentinel.continue';

    /** Flashed after an account was signed out for its state (suspended, disabled). */
    public const string AccountState = 'gcego-sentinel.account-state';

    /** How the signed-in session was established ("single" or "multi" factor). */
    public const string Assurance = 'gcego-sentinel.assurance';

    public const string SingleFactor = 'single';

    public const string MultiFactor = 'multi';

    /** The password-verified sign-in waiting for its second factor (user key, remember, expiry, attempts). */
    public const string PendingLogin = 'gcego-sentinel.pending-login';

    /** When the session signed in (unix time; absolute session lifetime). */
    public const string AuthenticatedAt = 'gcego-sentinel.authenticated-at';

    /** The last request of the signed-in session (unix time; idle timeout). */
    public const string LastActivity = 'gcego-sentinel.last-activity';

    /** A keyed fingerprint of the password hash the session signed in with. */
    public const string PasswordFingerprint = 'gcego-sentinel.password-fingerprint';

    /** The serialized WebAuthn options of the ceremony in progress (single use). */
    public const string PasskeyOptions = 'gcego-sentinel.passkey-options';

    /** The account's session epoch this session was valid for. */
    public const string Epoch = 'gcego-sentinel.epoch';
}
