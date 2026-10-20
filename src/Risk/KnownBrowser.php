<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Risk;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser an account has signed in from (table sentinel_known_browsers),
 * recognised by a random cookie whose keyed hash is stored. It only tells
 * "seen before" from "new"; it is not device identity and grants nothing.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $token_hash
 * @property string|null $label
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
final class KnownBrowser extends Model
{
    public $timestamps = false;

    protected $table = 'sentinel_known_browsers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime'];
    }
}
