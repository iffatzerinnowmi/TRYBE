<?php

namespace App\Console\Commands;

use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * DEMO HELPER — completes the next outstanding volunteer study.
 *
 * UnlockDemoSeeder leaves the demo participant one volunteer study short of
 * the unlock threshold. This command completes that study, which is the event
 * the unlock feature is watching for.
 *
 * It deliberately does NOT touch the unlock columns itself. All it does is
 * move a participation to COMPLETED — exactly what would happen in real life
 * when a researcher marks someone as finished. The unlock is then discovered
 * by FreeToPaidUnlockService on the next recalculate call. Keeping those two
 * things separate is the point: this command proves the service is doing the
 * counting, not the seeder.
 *
 * RUN:  php artisan trybe:unlock-next
 */
class UnlockNextCommand extends Command
{
    protected $signature = 'trybe:unlock-next {email=tanvir@trybe.test : Participant email}';

    protected $description = 'Complete the next outstanding volunteer study for the unlock demo';

    public function handle(): int
    {
        $participant = User::where('email', $this->argument('email'))->first();

        if (! $participant) {
            $this->error('No user with email ' . $this->argument('email'));
            return self::FAILURE;
        }

        // The earliest volunteer participation that is not yet completed.
        $participation = StudyParticipation::query()
            ->where('participant_id', $participant->id)
            ->where('stage', '!=', PipelineStage::COMPLETED->value)
            ->whereHas('study', fn ($q) => $q->where('incentive_type', 'volunteer'))
            ->orderBy('id')
            ->first();

        if (! $participation) {
            $this->warn('No outstanding volunteer study left. Re-run the seeder to reset:');
            $this->warn('  php artisan db:seed --class=UnlockDemoSeeder');
            return self::SUCCESS;
        }

        $participation->stage = PipelineStage::COMPLETED->value;
        $participation->completed_at = now();
        $participation->save();

        $this->info('Completed: ' . $participation->study->title);
        $this->info('Now POST /api/v1/participants/' . $participant->id . '/unlock-status/recalculate');

        return self::SUCCESS;
    }
}