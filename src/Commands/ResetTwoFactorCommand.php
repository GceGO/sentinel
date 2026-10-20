<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Commands;

use Gcego\Sentinel\Commands\Concerns\FindsUsers;
use Gcego\Sentinel\MultiFactor\TwoFactorAuthentication;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Illuminate\Console\Command;

/**
 * Account recovery for someone who lost their authenticator and their
 * recovery codes: an operator who verified the person's identity out of
 * band turns two-factor authentication off. Every session of the account
 * ends, the owner is notified, and the change is audited; when the account
 * must use two-factor authentication, it has to enroll again before it can
 * continue.
 */
final class ResetTwoFactorCommand extends Command
{
    use FindsUsers;

    protected $signature = 'gcego:sentinel-reset-two-factor {user : The account\'s id or sign-in identifier} {--force : Do not ask for confirmation}';

    protected $description = 'Turn two-factor authentication off for an account (identity verified out of band)';

    public function handle(TwoFactorAuthentication $twoFactor, SessionRepository $sessions, SessionSecurity $sessionSecurity): int
    {
        $user = $this->findUser((string) $this->argument('user'));

        if ($user === null) {
            $this->components->error('No such account.');

            return self::FAILURE;
        }

        if ($this->option('force') !== true && ! $this->confirm('Did you verify this person\'s identity out of band? Two-factor authentication will be turned off.')) {
            return self::FAILURE;
        }

        $twoFactor->disable($user, administrative: true);
        $sessions->deleteAll($user);
        $sessionSecurity->revokeAll($user);
        $this->components->info('Two-factor authentication is off and every session of the account has ended.');

        return self::SUCCESS;
    }
}
