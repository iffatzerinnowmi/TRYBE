<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AiClient;
use App\Services\SkillGapService;
use Illuminate\Console\Command;

/**
 * Pre-warm the skill-gap advice.  (Member 4)
 *
 *     php artisan trybe:advice:generate
 *     php artisan trybe:advice:generate --user=sarah.participant@trybe.test
 *
 * WHY THIS EXISTS
 * ---------------
 * No GET endpoint is allowed to call the AI provider, because a page load
 * must never block on a third party. So advice is generated either by an
 * explicit refresh button or here.
 *
 * Run it before a demo. Then the only network call during the demo is one
 * you chose to trigger.
 *
 * Safe to run repeatedly: SkillGapService::generate() skips anyone whose
 * analysis has not changed since their advice was produced.
 */
class GenerateSkillAdvice extends Command
{
    protected $signature = 'trybe:advice:generate
        {--user= : Only this participant, by email}
        {--force : Regenerate even when the analysis is unchanged}';

    protected $description = 'Generate skill-gap advice for participants, so no page load has to wait on the AI.';

    public function handle(SkillGapService $skillGap, AiClient $ai): int
    {
        if (! $ai->enabled()) {
            $this->warn('No AI_API_KEY configured. The deterministic analysis will still be');
            $this->warn('stored and rendered — only the advice half is skipped.');
            $this->line('');
        }

        $query = User::where('role', UserRole::PARTICIPANT->value)
            ->whereHas('participantProfile');

        if ($email = $this->option('user')) {
            $query->where('email', $email);
        }

        $participants = $query->get();

        if ($participants->isEmpty()) {
            $this->error('No matching participant found.');

            return self::FAILURE;
        }

        $generated = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($participants as $user) {
            if ($this->option('force')) {
                // Clearing the hash forces generate() past its "already
                // current" check without touching the stored advice.
                \App\Models\SkillGapAdvice::where('user_id', $user->id)
                    ->update(['inputs_hash' => '']);
            }

            $record = $skillGap->generate($user);

            $analysis = $record->analysis;

            if (($analysis['near_miss_count'] ?? 0) === 0) {
                $this->line(sprintf('  %-38s no near-miss studies (%s)',
                    $user->email, $analysis['reason'] ?? 'none'));
                $skipped++;
                continue;
            }

            if ($record->last_error) {
                $this->line(sprintf('  <fg=red>%-38s %s</>', $user->email, $record->last_error));
                $failed++;
                continue;
            }

            if ($record->advice) {
                $this->line(sprintf('  <fg=green>%-38s</> %s',
                    $user->email,
                    $record->advice['focus_skill'] ?? ($record->advice['headline'] ?? 'advice stored')));
                $generated++;
                continue;
            }

            $skipped++;
        }

        $this->line('');
        $this->info(sprintf(
            '%d participant(s): %d with advice, %d skipped, %d failed.',
            $participants->count(), $generated, $skipped, $failed
        ));

        return self::SUCCESS;
    }
}
