<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Accounts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An administrative security hold (table sentinel_security_holds): while
 * unreleased, the account cannot sign in and its sessions end. Holds are
 * never deleted, so the history stays auditable.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $reason
 * @property string|null $applied_by
 * @property Carbon $applied_at
 * @property Carbon|null $released_at
 * @property string|null $released_by
 * @property string|null $release_note
 */
final class SecurityHold extends Model
{
    public $timestamps = false;

    protected $table = 'sentinel_security_holds';

    protected $guarded = ['id'];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    protected function casts(): array
    {
        return ['applied_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }
}
