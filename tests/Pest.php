<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Sentinel\Tests\Fixtures\Models\Membership;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Gcego\Sentinel\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Livewire\Features\SupportTesting\Testable;

uses(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

const PASSWORD = 'Correct-Horse-Battery-9';

/**
 * A verified, active user whose password is PASSWORD.
 *
 * @param  array<string, mixed>  $attributes
 */
function user(array $attributes = []): User
{
    static $sequence = 0;
    $sequence++;

    return User::query()->create([
        'name' => 'User '.$sequence,
        'email' => 'user'.$sequence.'@example.test',
        'password' => PASSWORD,
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

/**
 * @param  list<string>  $permissions
 */
function member(User $user, int $institution, array $permissions = ['institution.access']): User
{
    Membership::query()->create(['user_id' => $user->id, 'institution_id' => $institution, 'permissions' => $permissions]);

    return $user;
}

/**
 * The SQL statements run by $callback.
 *
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();
    } finally {
        $queries = array_map(static fn (array $query): string => (string) $query['query'], DB::getQueryLog());
        DB::disableQueryLog();
    }

    return array_values($queries);
}

/**
 * The decoded Livewire snapshot of the first component named $name in $html.
 */
function snapshotOf(string $html, string $name): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES);

        if (str_contains($snapshot, '"name":"'.$name.'"')) {
            return $snapshot;
        }
    }

    return '';
}

/**
 * Collapse whitespace, for assertions on rendered HTML.
 */
function squish(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

/**
 * Record a measured value in build/performance.json (for the delivery report).
 *
 * @param  array<string, int|float>  $values
 */
function recordPerformance(array $values): void
{
    $file = dirname(__DIR__).'/build/performance.json';
    $current = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, (string) json_encode([...(is_array($current) ? $current : []), ...$values], JSON_PRETTY_PRINT));
}

/**
 * Like a browser submit: deferred wire:model values and the action in one
 * Livewire request (Sentinel clears secrets before every response, so a
 * separate set() round trip would not keep them).
 *
 * @param  array<string, mixed>  $updates
 * @param  list<mixed>  $params
 */
function submit(Testable $component, string $method, array $updates, array $params = []): Testable
{
    return $component->update(calls: [['method' => $method, 'params' => $params, 'path' => '']], updates: $updates);
}

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);
