<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Membership extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
