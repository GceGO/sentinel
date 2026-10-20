<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

use Gcego\Sentinel\SentinelConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A stored security audit entry (table sentinel_security_events). Rows are
 * append-only: Sentinel never updates them, and `php artisan model:prune`
 * deletes those older than gcego-sentinel.audit.retention_days.
 *
 * @property int $id
 * @property string $type
 * @property string $outcome
 * @property string|null $user_key
 * @property array<string, string|int|bool|null>|null $context
 * @property string|null $session_reference
 * @property string|null $tenant
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 */
final class SecurityEventRecord extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $table = 'sentinel_security_events';

    protected $guarded = ['id'];

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $days = app(SentinelConfig::class)->auditRetentionDays;

        return self::query()->where('occurred_at', '<', now()->subDays($days));
    }

    /**
     * The newest entries of one user, for their own account page.
     *
     * @return Collection<int, self>
     */
    public static function recentFor(string|int $userKey, int $limit = 10): Collection
    {
        return self::query()->where('user_key', (string) $userKey)->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get();
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
