<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Sessions;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;

/**
 * The current user's stored sessions. Laravel only keeps a per-user session
 * inventory with the "database" session driver (sessions.user_id); for any
 * other driver nothing is listed (never invented) and the session fingerprint (sessions.bind_to_password) ends other sessions
 * after a password change.
 */
final readonly class SessionRepository
{
    public function __construct(private Repository $config, private ConnectionResolverInterface $connections) {}

    public function supported(): bool
    {
        return $this->config->get('session.driver') === 'database';
    }

    /**
     * @return list<BrowserSession>
     */
    public function forUser(Authenticatable $user, string $currentId, int $limit = 50): array
    {
        if (! $this->supported()) {
            return [];
        }

        $sessions = [];

        foreach ($this->query($user)->orderByDesc('last_activity')->limit($limit)->get(['id', 'ip_address', 'user_agent', 'last_activity']) as $row) {
            $id = is_object($row) && isset($row->id) && is_scalar($row->id) ? (string) $row->id : '';

            if ($id === '') {
                continue;
            }

            $sessions[] = new BrowserSession(
                self::digest($id),
                UserAgent::parse(isset($row->user_agent) && is_string($row->user_agent) ? $row->user_agent : null),
                isset($row->ip_address) && is_string($row->ip_address) ? $row->ip_address : null,
                (new DateTimeImmutable)->setTimestamp(isset($row->last_activity) && is_numeric($row->last_activity) ? (int) $row->last_activity : 0),
                hash_equals($id, $currentId),
            );
        }

        return $sessions;
    }

    /**
     * Delete every stored session of the user except the current one.
     */
    public function deleteOthers(Authenticatable $user, string $currentId): int
    {
        return $this->supported() ? $this->query($user)->where('id', '!=', $currentId)->delete() : 0;
    }

    /**
     * Delete every stored session of the user (password reset, security hold).
     */
    public function deleteAll(Authenticatable $user): int
    {
        return $this->supported() ? $this->query($user)->delete() : 0;
    }

    /**
     * Delete one of the user's other sessions by its digest. Sessions of
     * other users and the current session cannot be deleted.
     */
    public function delete(Authenticatable $user, string $digest, string $currentId): bool
    {
        if (! $this->supported() || preg_match('/^[0-9a-f]{64}$/', $digest) !== 1) {
            return false;
        }

        foreach ($this->query($user)->pluck('id') as $id) {
            if (is_scalar($id) && hash_equals(self::digest((string) $id), $digest) && ! hash_equals((string) $id, $currentId)) {
                return $this->query($user)->where('id', (string) $id)->delete() === 1;
            }
        }

        return false;
    }

    public static function digest(string $id): string
    {
        return hash('sha256', 'gcego-sentinel|'.$id);
    }

    private function query(Authenticatable $user): Builder
    {
        $connection = $this->config->get('session.connection');
        $table = $this->config->get('session.table', 'sessions');

        return $this->connections->connection(is_string($connection) ? $connection : null)
            ->table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $user->getAuthIdentifier());
    }
}
