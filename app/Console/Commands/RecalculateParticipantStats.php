<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CredentialService;
use App\Services\ReliabilityService;
use Illuminate\Console\Command;

/**
 * Recalculates credential level and reliability score for every participant.
 *
 *   php artisan trybe:recalculate
 *   php artisan trybe:recalculate --email=iffat@trybe.test
 *
 * In production this would run nightly on a schedule. It exists here to prove
 * that the numbers on screen are derived, not stored by hand — you can wipe
 * the columns, run this, and watch them come back correct.
 */
class RecalculateParticipantStats extends Command
{
    protected $signature = 'trybe:recalculate {--email= : Only this participant}';

    protected $description = 'Recalculate credential level and reliability score for participants';

    public function handle(CredentialService $credentials, ReliabilityService $reliability): int
    {
        $query = User::where('role', UserRole::PARTICIPANT)->has('participantProfile');

        if ($email = $this->option('email')) {
            $query->where('email', $email);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No matching participants found.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($users as $user) {
            $c = $credentials->recalculate($user);
            $r = $reliability->recalculate($user);

            $rows[] = [
                $user->name,
                $c['count'],
                $c['to']->label() . ($c['promoted'] ? '  ↑' : ''),
                $r['score'] . ($r['changed'] ? '  (was ' . $r['from'] . ')' : ''),
            ];
        }

        $this->table(['Participant', 'Completed', 'Credential', 'Reliability'], $rows);
        $this->info('Recalculated ' . count($rows) . ' participants.');

        return self::SUCCESS;
    }
}
