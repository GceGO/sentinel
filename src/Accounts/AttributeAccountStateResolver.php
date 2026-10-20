<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Accounts;

use BackedEnum;
use Gcego\Sentinel\Contracts\AccountStateResolver;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use UnitEnum;

/**
 * Reads gcego-sentinel.account_states.attribute on the user (a string, an
 * enum, or a value with an "is" method) and compares it with the
 * configured suspended, disabled and review values. Without an attribute every
 * account is active.
 */
final readonly class AttributeAccountStateResolver implements AccountStateResolver
{
    public function __construct(private SentinelConfig $config) {}

    public function state(Authenticatable $user): AccountState
    {
        $attribute = $this->config->accountStateAttribute;

        if ($attribute === null) {
            return AccountState::Active;
        }

        $value = self::normalize(data_get($user, $attribute));

        return match (true) {
            $value === null => AccountState::Active,
            in_array($value, $this->config->disabledValues, true) => AccountState::Disabled,
            in_array($value, $this->config->suspendedValues, true) => AccountState::Suspended,
            in_array($value, $this->config->reviewValues, true) => AccountState::Review,
            default => AccountState::Active,
        };
    }

    private static function normalize(mixed $value): ?string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_object($value) && property_exists($value, 'value') && is_scalar($value->value) => (string) $value->value,
            is_string($value), is_int($value) => (string) $value,
            default => null,
        };
    }
}
