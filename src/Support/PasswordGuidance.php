<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Gcego\Sentinel\Passwords\PasswordPolicy;

/**
 * Human-readable password requirements from Sentinel's PasswordPolicy,
 * shown next to new-password fields (and used by the script's strength
 * hints). The server-side rule stays authoritative.
 */
final class PasswordGuidance
{
    /**
     * @return array{min: int, mixedCase: bool, letters: bool, numbers: bool, symbols: bool, uncompromised: bool}
     */
    public static function rules(): array
    {
        $applied = app(PasswordPolicy::class)->rule()->appliedRules();
        $min = $applied['min'] ?? 8;

        return [
            'min' => is_int($min) ? $min : 8,
            'mixedCase' => ($applied['mixedCase'] ?? false) === true,
            'letters' => ($applied['letters'] ?? false) === true,
            'numbers' => ($applied['numbers'] ?? false) === true,
            'symbols' => ($applied['symbols'] ?? false) === true,
            'uncompromised' => ($applied['uncompromised'] ?? false) === true,
        ];
    }

    /**
     * @return array<string, string> rule => translated line
     */
    public static function lines(): array
    {
        $rules = self::rules();
        $lines = ['min' => __('gcego-sentinel::messages.password_rules.min', ['min' => $rules['min']])];

        foreach (['mixedCase' => 'mixed_case', 'letters' => 'letters', 'numbers' => 'numbers', 'symbols' => 'symbols', 'uncompromised' => 'uncompromised'] as $rule => $key) {
            if ($rules[$rule]) {
                $lines[$rule] = __('gcego-sentinel::messages.password_rules.'.$key);
            }
        }

        return array_map(static fn (mixed $line): string => is_string($line) ? $line : '', $lines);
    }
}
