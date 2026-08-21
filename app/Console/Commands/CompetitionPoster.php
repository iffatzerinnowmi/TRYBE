<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Services\CompetitionService;
use Illuminate\Console\Command;

/**
 * "Why can't this account post a competition?"  (Member 4)
 *
 *     php artisan trybe:competitions:poster --email=sarah.researcher@trybe.test
 *     php artisan trybe:competitions:poster --email=... --verify
 *
 * Posting is limited to VERIFIED researchers and organizations, plus admins.
 * An account created by hand is `unverified`, so the post panel is hidden —
 * correctly, but invisibly. This says so out loud rather than leaving it to
 * be inferred from a missing button.
 */
class CompetitionPoster extends Command
{
    protected $signature = 'trybe:competitions:poster
                            {--email= : The account to inspect}
                            {--verify : Mark this account verified so it can post}';

    protected $description = 'Explain whether an account may post competitions, and optionally verify it';

    public function handle(CompetitionService $competitions): int
    {
        $email = $this->option('email');

        if (! $email) {
            // No email given: list everyone who CAN post, which is usually
            // the actual question ("who do I log in as?").
            $this->line('');
            $this->line('Accounts that can post competitions right now:');

            $rows = User::whereIn('role', [
                    UserRole::RESEARCHER->value,
                    UserRole::ORGANIZATION->value,
                    UserRole::ADMIN->value,
                ])
                ->orderBy('role')
                ->get()
                ->filter(fn (User $u) => $competitions->canPost($u))
                // ->value on both: the columns are cast to enums, and a table
                // cell needs a scalar.
                ->map(fn (User $u) => [$u->email, $u->role->value, $u->verification_status?->value])
                ->values()
                ->all();

            $this->table(['email', 'role', 'verification'], $rows ?: [['(nobody)', '', '']]);
            $this->line('  All seeded accounts use the password: password');

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No account with email {$email}.");

            return self::FAILURE;
        }

        $this->line('');
        $this->line('  email        ' . $user->email);
        $this->line('  role         ' . $user->role->value);
        $this->line('  verification ' . ($user->verification_status?->value ?: '(none)'));
        $this->line('');

        if ($competitions->canPost($user)) {
            $this->info('✓ This account CAN post competitions.');
            $this->line('  If the panel is still missing, the front end is stale — run: npm run build');

            return self::SUCCESS;
        }

        // Say precisely which rule failed, not just "no".
        if (! in_array($user->role, [UserRole::RESEARCHER, UserRole::ORGANIZATION, UserRole::ADMIN], true)) {
            $this->error('✗ Cannot post: only researchers, organizations and admins may post.');
            $this->line('  A participant account can browse and save, never post. That is by design.');

            return self::SUCCESS;
        }

        $this->error('✗ Cannot post: the account is not verified.');
        $this->line('  Researchers and organizations must be verified. Admins are exempt.');
        $this->line('');

        if (! $this->option('verify')) {
            $this->line('  To verify it for the demo:');
            $this->line("      php artisan trybe:competitions:poster --email={$user->email} --verify");

            return self::SUCCESS;
        }

        /*
        | verification_status is Member 1's column. Writing it here is a
        | demo convenience, not part of the feature — the real path is her
        | admin approval screen, which is exactly what this shortcuts.
        | Announced rather than done quietly.
        */
        $user->update(['verification_status' => VerificationStatus::VERIFIED]);

        $this->newLine();
        $this->info("Verified {$user->email}. They can post competitions now.");
        $this->warn('Note: verification_status belongs to Member 1\'s verification feature.');
        $this->warn('This is a demo shortcut for one account — the real route is her admin screen.');

        return self::SUCCESS;
    }
}
