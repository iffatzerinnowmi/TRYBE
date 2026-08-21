<?php

namespace App\Console\Commands;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\Referral;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Console\Command;

/**
 * Demo helper — make a referrer's PENDING referrals qualify.  (Member 4)
 *
 *     php artisan trybe:demo:qualify --referrer=sarah.researcher@trybe.test
 *     php artisan trybe:demo:qualify --referrer=... --no-sync
 *
 * WHY THIS EXISTS
 * ---------------
 * A referral qualifies when the referred person does something real:
 *
 *   referred RESEARCHER  -> posts their first study
 *   referred PARTICIPANT -> completes their first study
 *
 * Neither is reachable from the API during a live demo:
 *
 *   - POST /studies is a WEB route (Member 2). There is no API for it, so
 *     Postman cannot post a study.
 *   - Marking a participation COMPLETED is Member 2's pipeline tracker,
 *     which does not exist yet at all.
 *
 * So this command performs those two real-world actions, and then lets the
 * ordinary ReferralService::sync() notice them. It does NOT set status =
 * qualified directly, and it does NOT grant any reward itself — it seeds the
 * evidence and the real code path draws the conclusion. That distinction is
 * the point: if the qualification logic were broken, this command would not
 * hide it.
 *
 * It touches studies and study_participations, which are Member 2's tables,
 * and only ever for users who are pending referrals of the named referrer.
 * It is a demo helper, not part of the feature.
 */
class DemoQualify extends Command
{
    protected $signature = 'trybe:demo:qualify
        {--referrer= : Email of the referrer whose pending referrals should qualify}
        {--no-sync : Seed the evidence but do not run sync(), so you can run it from Postman}';

    protected $description = 'Make a referrer\'s pending referrals qualify, for a live demo.';

    public function handle(ReferralService $referrals): int
    {
        $email = $this->option('referrer');

        if (! $email) {
            $this->error('Pass --referrer=<email>. Run trybe:asg3:check if you need reminding.');

            return self::FAILURE;
        }

        $referrer = User::where('email', $email)->first();

        if (! $referrer) {
            $this->error('No account with email ' . $email);

            return self::FAILURE;
        }

        $pending = Referral::with('referredUser')
            ->where('referrer_id', $referrer->id)
            ->pending()
            ->get();

        if ($pending->isEmpty()) {
            $this->warn('No pending referrals for ' . $email . '. Sign somebody up through the '
                . 'referral link first.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Seeding real qualifying activity for ' . $pending->count() . ' pending referral(s):');

        foreach ($pending as $referral) {
            $user = $referral->referredUser;

            if (! $user) {
                continue;
            }

            if ($user->role === UserRole::RESEARCHER) {
                $this->postStudyFor($user);
                continue;
            }

            $this->completeStudyFor($user);
        }

        if ($this->option('no-sync')) {
            $this->line('');
            $this->info('Evidence seeded. Nothing has qualified YET — the referrals are still');
            $this->info('pending until sync() runs. Fire POST /api/v1/referrals/me/sync in');
            $this->info('Postman now, and watch granted_now come back as 1.');
            $this->line('');

            return self::SUCCESS;
        }

        $result = $referrals->sync($referrer);
        $p = $result['progress'];

        $this->line('');
        $this->info('sync() run:');
        $this->line('  qualified          ' . $p['qualified_count']);
        $this->line('  counts for reward  ' . $p['eligible_count'] . ' / ' . $p['required_to_unlock']);
        $this->line('  granted just now   ' . $result['granted']->count());
        $this->line('');

        return self::SUCCESS;
    }

    /** A referred researcher qualifies by posting their first study. */
    private function postStudyFor(User $researcher): void
    {
        if (Study::where('researcher_id', $researcher->id)->exists()) {
            $this->line('  = ' . $researcher->email . ' already has a study');

            return;
        }

        $study = Study::create([
            'researcher_id'       => $researcher->id,
            'title'               => 'First study by ' . $researcher->name,
            'description'         => 'Posted during the referral demo.',
            'category'            => 'Survey',
            'method'              => 'online',
            'duration_minutes'    => 20,
            'incentive_type'      => IncentiveType::VOLUNTEER->value,
            'compensation_amount' => 0,
            'slots'               => 5,
            'status'              => StudyStatus::OPEN->value,
            'participants_count'  => 0,
            'irb_flagged'         => false,
        ]);

        $this->line('  + ' . $researcher->email . ' posted study #' . $study->id);
    }

    /** A referred participant qualifies by completing their first study. */
    private function completeStudyFor(User $participant): void
    {
        $already = StudyParticipation::where('participant_id', $participant->id)
            ->whereIn('stage', [PipelineStage::COMPLETED->value, PipelineStage::PAID->value])
            ->exists();

        if ($already) {
            $this->line('  = ' . $participant->email . ' already completed a study');

            return;
        }

        $study = Study::where('status', StudyStatus::OPEN)->orderBy('id')->first();

        if (! $study) {
            $this->warn('  ! no open study to complete for ' . $participant->email);

            return;
        }

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $participant->id],
            ['stage' => PipelineStage::COMPLETED->value, 'completed_at' => now()]
        );

        $this->line('  + ' . $participant->email . ' completed study #' . $study->id);
    }
}
