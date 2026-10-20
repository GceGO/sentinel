<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Accounts;

use Gcego\Security\Permissions\Permission;
use Gcego\Security\SecurityManager;
use Gcego\Sentinel\Events\SecurityHoldApplied;
use Gcego\Sentinel\Events\SecurityHoldReleased;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\RequestMemo;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Administrative security holds. Applying or releasing one needs the
 * gcego/security permission "sentinel.holds.manage" (an $actor of null
 * means a trusted operator: the artisan commands), a reason, and is
 * audited. Applying a hold ends every session of the account and cycles
 * its remember-me token; releasing it is a separate, recorded decision.
 * Nobody can hold or release their own account.
 */
final readonly class SecurityHolds
{
    public const string Permission = 'sentinel.holds.manage';

    public function __construct(
        private SecurityManager $security,
        private SessionRepository $sessions,
        private SessionSecurity $sessionSecurity,
        private Dispatcher $events,
        private SecurityNotifier $notifier,
    ) {}

    /**
     * The account's unreleased hold (looked up once per request).
     */
    public function active(Authenticatable $user): ?SecurityHold
    {
        return RequestMemo::remember('hold.'.UserKey::of($user), static function () use ($user): ?SecurityHold {
            try {
                return SecurityHold::query()->active()->where('user_id', $user->getAuthIdentifier())->latest('applied_at')->first();
            } catch (Throwable) {
                // Without the table no hold can exist (gcego:sentinel-inspect reports the missing migration).
                return null;
            }
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function apply(Authenticatable $user, string $reason, ?Authenticatable $actor = null): SecurityHold
    {
        $this->authorize($user, $actor);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('A security hold needs a reason of at most 1000 characters.');
        }

        RequestMemo::forget('hold.'.UserKey::of($user));
        $hold = DB::transaction(function () use ($user, $reason, $actor): SecurityHold {
            if (($existing = $this->active($user)) !== null) {
                return $existing;
            }

            $hold = SecurityHold::query()->create([
                'user_id' => $user->getAuthIdentifier(),
                'reason' => $reason,
                'applied_by' => $actor === null ? null : (string) UserKey::of($actor),
                'applied_at' => Date::now(),
            ]);

            if ($user instanceof Model && $user->getRememberTokenName() !== '') {
                $user->setRememberToken(Str::random(60));
                $user->save();
            }

            return $hold;
        });

        RequestMemo::forget('hold.'.UserKey::of($user));
        $this->sessions->deleteAll($user);
        $this->sessionSecurity->revokeAll($user);
        $this->events->dispatch(new SecurityHoldApplied(UserKey::of($user), $hold->id, $actor === null ? null : (string) UserKey::of($actor)));
        $this->notifier->send($user, 'security_hold_applied');

        return $hold;
    }

    /**
     * @throws AuthorizationException
     */
    public function release(Authenticatable $user, string $note, ?Authenticatable $actor = null): ?SecurityHold
    {
        $this->authorize($user, $actor);
        $hold = $this->active($user);

        if ($hold === null) {
            return null;
        }

        RequestMemo::forget('hold.'.UserKey::of($user));
        $hold->forceFill([
            'released_at' => Date::now(),
            'released_by' => $actor === null ? null : (string) UserKey::of($actor),
            'release_note' => mb_substr(trim($note), 0, 1000),
        ])->save();

        $this->events->dispatch(new SecurityHoldReleased(UserKey::of($user), $hold->id, $actor === null ? null : (string) UserKey::of($actor)));
        $this->notifier->send($user, 'security_hold_released');

        return $hold;
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(Authenticatable $user, ?Authenticatable $actor): void
    {
        if ($actor === null) {
            return;
        }

        if ((string) UserKey::of($actor) === (string) UserKey::of($user)) {
            throw new AuthorizationException('Security holds cannot be applied to or released from your own account.');
        }

        $allowed = $this->security->actingAs($actor, null, fn (): bool => $this->security->check(new Permission(self::Permission))->allowed);

        if (! $allowed) {
            throw new AuthorizationException('Managing security holds needs the "'.self::Permission.'" permission.');
        }
    }
}
