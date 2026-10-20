<?php

declare(strict_types=1);

namespace Gcego\Sentinel\MultiFactor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One recovery code, stored as a keyed hash (table sentinel_recovery_codes).
 * The plaintext is only ever shown in the response that generated it.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $code_hash
 * @property Carbon|null $used_at
 */
final class RecoveryCode extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sentinel_recovery_codes';

    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['used_at' => 'immutable_datetime'];
    }
}
