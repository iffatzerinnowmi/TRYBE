<?php

namespace Database\Seeders;

use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;

/**
 * DEMO SEEDER — free-to-paid unlock (Member 3's feature)
 *
 * WHY THIS EXISTS
 * ---------------
 * The main DatabaseSeeder contains exactly one volunteer-incentive study and
 * nobody has completed it, so FreeToPaidUnlockService always counts zero and
 * the feature can never be demonstrated. This seeder adds the missing data.
 *
 * It is a SEPARATE seeder on purpose. It is not called from DatabaseSeeder,
 * so it never runs by accident, and it does not require migrate:fresh on the
 * shared Railway database.
 *
 * WHAT IT CREATES
 * ---------------
 *   - Five VOLUNTEER studies (the only kind that counts toward unlock).
 *   - Four of them COMPLETED by the demo participant.
 *   - A fifth left at APPLIED, so the unlock has not happened yet.
 *
 * That leaves the participant one study short, which is the interesting
 * state: `recalculate` reports "1 more free study to go". Running
 * `php artisan trybe:unlock-next` completes the fifth, and the next
 * `recalculate` fires the actual unlock.
 *
 * It is safe to run repeatedly. Studies are matched by title, participations
 * by (study, participant), and the participant's unlock fields are reset each
 * time — so the demo can be replayed as often as needed.
 *
 * RUN:  php artisan db:seed --class=UnlockDemoSeeder
 */
class UnlockDemoSeeder extends Seeder
{
    /** How many of the five are pre-completed. The fifth is left pending. */
    private const PRE_COMPLETED = 4;

    public function run(): void
    {
        $participant = User::where('email', 'tanvir@trybe.test')->first();

        if (! $participant) {
            $this->command->error('Participant iffat@trybe.test not found. Run the main DatabaseSeeder first.');
            return;
        }

        $profile = ParticipantProfile::where('user_id', $participant->id)->first();

        if (! $profile) {
            $this->command->error('That participant has no participant_profiles row — the unlock API returns 404 without one.');
            return;
        }

        $researcher = User::where('role', UserRole::RESEARCHER)->first();

        if (! $researcher) {
            $this->command->error('No researcher found to own the studies.');
            return;
        }

        $titles = [
            'Volunteer: campus noise diary',
            'Volunteer: reading speed self-test',
            'Volunteer: icon recognition task',
            'Volunteer: short-term recall check',
            'Volunteer: colour naming task',
        ];

        foreach ($titles as $i => $title) {

            // firstOrCreate keyed on title, so re-running does not pile up
            // duplicate studies.
            $study = Study::firstOrCreate(
                ['title' => $title],
                [
                    'researcher_id'       => $researcher->id,
                    'description'         => 'Unpaid volunteer study used to demonstrate the free-to-paid unlock.',
                    'category'            => 'Survey',
                    'method'              => 'online',
                    'duration_minutes'    => 15,
                    'incentive_type'      => 'volunteer',   // the ONLY type that counts
                    'compensation_amount' => 0,
                    'slots'               => 20,
                    'status'              => 'closed',
                    'participants_count'  => 1,
                    'irb_document_path'   => 'irb-documents/volunteer-irb.pdf',
                    'irb_flagged'         => false,
                    'irb_board'           => 'BRAC University IRB',
                    'irb_ref'             => 'IRB-2026-2' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                ]
            );

            // The first four are completed; the fifth stays at APPLIED so the
            // participant sits one study short of the threshold.
            $isCompleted = $i < self::PRE_COMPLETED;

            $participation = StudyParticipation::firstOrNew([
                'study_id'       => $study->id,
                'participant_id' => $participant->id,
            ]);

            $participation->stage = $isCompleted
                ? PipelineStage::COMPLETED->value
                : PipelineStage::APPLIED->value;

            $participation->completed_at = $isCompleted ? now()->subWeeks(5 - $i) : null;
            $participation->save();
        }
        // Participant 7 was used for an earlier run of this demo. Strip the
        // demo studies off anyone who is no longer the demo participant, so
        // their completed-studies count returns to what the main seeder set.
        $demoStudyIds = Study::whereIn('title', $titles)->pluck('id');

        $strays = StudyParticipation::whereIn('study_id', $demoStudyIds)
            ->where('participant_id', '!=', $participant->id)
            ->pluck('participant_id')
            ->unique();

        StudyParticipation::whereIn('study_id', $demoStudyIds)
            ->where('participant_id', '!=', $participant->id)
            ->delete();

        foreach ($strays as $strayId) {
            ParticipantProfile::where('user_id', $strayId)->update([
                'free_studies_completed'   => 0,
                'paid_studies_unlocked'    => false,
                'paid_studies_unlocked_at' => null,
            ]);
        }
        // Reset the unlock so the demo always starts from "locked", even on a
// second run. These are Member 3's columns; nothing else is touched.
//
// Cycle fields are set explicitly here rather than left to the
// observer, because firstOrNew() on an already-COMPLETED participation
// doesn't trigger a stage change on re-run — so the observer would
// silently skip recording it a second time.
        $completedVolunteerCount = StudyParticipation::whereIn('study_id', $demoStudyIds)
            ->where('participant_id', $participant->id)
            ->where('stage', PipelineStage::COMPLETED->value)
            ->count();

        $cycleTarget = (int) config('platform.volunteer_cycle_target');

        $profile->fill([
            'free_studies_completed'   => 0,
            'paid_studies_unlocked'    => false,
            'paid_studies_unlocked_at' => null,
            'total_volunteer_count'    => $completedVolunteerCount,
            'volunteer_progress'       => min($completedVolunteerCount, $cycleTarget),
            'paid_used'                => 0,
        ])->save();

        // Clear any unlock notification from a previous run, so the bell shows
        // a genuinely new one when the crossing happens.
        UserNotification::where('user_id', $participant->id)
            ->where('type', 'unlock')
            ->delete();

        $this->command->info('Unlock demo ready for ' . $participant->name . ' (user id ' . $participant->id . ').');
        $this->command->info(self::PRE_COMPLETED . ' of ' . count($titles) . ' volunteer studies completed — one short of the threshold.');
        $this->command->info('Next: GET /api/v1/participants/' . $participant->id . '/unlock-status');
    }
}