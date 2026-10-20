<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Commands;

use Gcego\Sentinel\Accounts\SecurityHolds;
use Gcego\Sentinel\Commands\Concerns\FindsUsers;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Console\Command;

/**
 * Place an account on security hold (it cannot sign in, every session ends).
 * The operator running the command is trusted; the hold is audited.
 */
final class HoldAccountCommand extends Command
{
    use FindsUsers;

    protected $signature = 'gcego:sentinel-hold {user : The account\'s id or sign-in identifier} {--reason= : Why (required, kept in the audit trail)}';

    protected $description = 'Place an account on security hold';

    public function handle(SecurityHolds $holds): int
    {
        $user = $this->findUser((string) $this->argument('user'));
        $reason = trim((string) $this->option('reason'));

        if ($user === null) {
            $this->components->error('No such account.');

            return self::FAILURE;
        }

        if ($reason === '') {
            $this->components->error('Give a --reason.');

            return self::FAILURE;
        }

        $hold = $holds->apply($user, $reason);
        $this->components->info(sprintf('Account %s is on security hold #%d; its sessions have ended.', (string) UserKey::of($user), $hold->id));

        return self::SUCCESS;
    }
}
