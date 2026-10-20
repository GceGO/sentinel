<?php

declare(strict_types=1);

namespace Gcego\Sentinel\MultiFactor;

use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\AppKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Recovery codes: 4 groups of 5 characters from a 32-letter alphabet
 * without look-alikes (100 bits from random_int()), stored only as
 * HMAC-SHA256 keyed with APP_KEY (previous keys are accepted on use), never
 * in plaintext or reversibly.
 *
 * redeem() is atomic: the conditional UPDATE marks a code used only when it
 * still is unused, so two concurrent requests cannot both redeem it.
 */
final readonly class RecoveryCodes
{
    public const string Alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ0123456789';

    public function __construct(
        private SentinelConfig $config,
        private Repository $settings,
    ) {}

    /**
     * Replace every code of the user with new ones; returns the plaintext codes (show them once).
     *
     * @return list<string>
     */
    public function generate(Authenticatable $user): array
    {
        $codes = [];

        while (count($codes) < $this->config->recoveryCodeCount) {
            $code = self::random();
            $codes[self::normalize($code)] = $code;
        }

        DB::transaction(function () use ($user, $codes): void {
            $this->query($user)->delete();
            $now = Date::now();

            foreach (array_keys($codes) as $normalized) {
                RecoveryCode::query()->create(['user_id' => $user->getAuthIdentifier(), 'code_hash' => $this->hash($normalized), 'created_at' => $now]);
            }
        });

        return array_values($codes);
    }

    /**
     * Use one code. True only for the request that marked it used.
     */
    public function redeem(Authenticatable $user, #[\SensitiveParameter] string $code): bool
    {
        $normalized = self::normalize($code);

        if (strlen($normalized) < 10 || strlen($normalized) > 64) {
            return false;
        }

        $hashes = array_map(fn (string $key): string => self::hmac($normalized, $key), $this->keys());

        return $this->query($user)->whereIn('code_hash', $hashes)->whereNull('used_at')->update(['used_at' => Date::now()]) === 1;
    }

    public function remaining(Authenticatable $user): int
    {
        return $this->query($user)->whereNull('used_at')->count();
    }

    public function clear(Authenticatable $user): void
    {
        $this->query($user)->delete();
    }

    public static function normalize(#[\SensitiveParameter] string $code): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $code));
    }

    private static function random(): string
    {
        $groups = [];

        for ($group = 0; $group < 4; $group++) {
            $chars = '';

            for ($i = 0; $i < 5; $i++) {
                $chars .= self::Alphabet[random_int(0, strlen(self::Alphabet) - 1)];
            }

            $groups[] = $chars;
        }

        return implode('-', $groups);
    }

    private function hash(#[\SensitiveParameter] string $normalized): string
    {
        return self::hmac($normalized, $this->keys()[0]);
    }

    private static function hmac(#[\SensitiveParameter] string $normalized, #[\SensitiveParameter] string $key): string
    {
        return hash_hmac('sha256', 'gcego-sentinel:recovery-code|'.$normalized, $key);
    }

    /**
     * APP_KEY first, then APP_PREVIOUS_KEYS.
     *
     * @return non-empty-list<string>
     */
    private function keys(): array
    {
        $previous = $this->settings->get('app.previous_keys', []);
        $keys = [AppKey::get($this->settings)];

        foreach (is_array($previous) ? $previous : [] as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return Builder<RecoveryCode>
     */
    private function query(Authenticatable $user): Builder
    {
        return RecoveryCode::query()->where('user_id', $user->getAuthIdentifier());
    }
}
