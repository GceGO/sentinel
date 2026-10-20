<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Commands;

use Gcego\Sentinel\Accounts\SecurityHolds;
use Gcego\Sentinel\Commands\Concerns\FindsUsers;
use Illuminate\Console\Command;

/**
 * Release an account's security hold (audited, with a note).
 */
final class ReleaseAccountCommand extends Command
{
    use FindsUsers;

    protected $signature = 'gcego:sentinel-release {user : The account\'s id or sign-in identifier} {--note= : How the review concluded (required)}';

    protected $description = 'Release an account\'s security hold';

    public function handle(SecurityHolds $holds): int
    {
        $user = $this->findUser((string) $this->argument('user'));
        $note = trim((string) $this->option('note'));

        if ($user === null || $note === '') {
            $this->components->error($user === null ? 'No such account.' : 'Give a --note.');

            return self::FAILURE;
        }

        if ($holds->release($user, $note) === null) {
            $this->components->warn('The account has no active security hold.');

            return self::SUCCESS;
        }

        $this->components->info('The security hold is released.');

        return self::SUCCESS;
    }
}
