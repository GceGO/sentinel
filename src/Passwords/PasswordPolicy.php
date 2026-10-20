<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Passwords;

use Closure;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Validation\Rules\Password;

/**
 * The password rules for every new password (registration, reset, change):
 * gcego-sentinel.passwords.min_length to max_length characters, optionally
 * not found in known breaches (Have I Been Pwned range API, k-anonymity),
 * and never longer than the hasher can store: bcrypt reads only the first
 * 72 bytes, so longer passwords are refused instead of silently truncated.
 * No composition rules (NIST SP 800-63B).
 */
final readonly class PasswordPolicy
{
    public const int BcryptLimit = 72;

    public function __construct(private SentinelConfig $config, private Repository $settings) {}

    public function rule(): Password
    {
        $rule = Password::min($this->config->passwordMinLength)->max($this->config->passwordMaxLength);

        return $this->config->uncompromisedPasswords ? $rule->uncompromised($this->config->uncompromisedThreshold) : $rule;
    }

    /**
     * @return list<mixed>
     */
    public function rules(bool $confirmed = true): array
    {
        return array_values(array_filter(['required', 'string', $this->rule(), $this->storable(), $confirmed ? 'confirmed' : null]));
    }

    /**
     * Rules for a password typed to prove identity (sign-in, confirmation):
     * bounded so a huge input cannot make hashing expensive.
     *
     * @return list<string>
     */
    public static function currentRules(): array
    {
        return ['required', 'string', 'max:4096'];
    }

    private function storable(): ?Closure
    {
        if ($this->settings->get('hashing.driver', 'bcrypt') !== 'bcrypt') {
            return null;
        }

        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && strlen($value) > self::BcryptLimit) {
                $fail(__('gcego-sentinel::messages.password_rules.too_long', ['max' => self::BcryptLimit]));
            }
        };
    }
}
