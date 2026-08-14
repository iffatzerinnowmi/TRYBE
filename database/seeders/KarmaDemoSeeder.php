<?php

namespace Database\Seeders;

use App\Enums\KarmaSource;
use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\StudyReview;
use App\Models\User;
use App\Services\KarmaService;
use Illuminate\Database\Seeder;

/**
 * Demonstrates the karma system WITHOUT waiting on Member 2's pipeline
 * feature. This does not call KarmaService::earn() for study_completed
 * or review_left directly — it creates the underlying StudyParticipation
 * / StudyReview rows exactly the way a real controller eventually will,
 * and lets StudyParticipationObserver / StudyReviewObserver do the actual
 * karma write. That's the point: this seeder stands in for a controller
 * that doesn't exist yet, not for KarmaService itself.
 *
 * Each run completes the NEXT uncompleted study for this participant —
 * a genuinely new action each time, so the balance visibly grows on
 * every run. Once every seeded study is completed, it stops and says
 * so, instead of quietly inflating the balance forever.
 *
 * session_on_time is the one source with no real trigger anywhere in the
 * schema (no scheduled-time column exists), so it's awarded directly,
 * once, as a labeled demo transaction.
 */
class KarmaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $karma = app(KarmaService::class);

        $participant = User::where('role', UserRole::PARTICIPANT)->first();

        if (! $participant) {
            $this->command->warn('Need at least one participant seeded first — run DatabaseSeeder.');
            return;
        }

        $before = $karma->balanceFor($participant);

        // ---- STUDY_COMPLETED + REVIEW_LEFT, via the real triggers ----
        // Find a study this participant has NOT already completed, so
        // every run is a genuinely new event, not a repeat of the last one.
        $study = Study::whereDoesntHave('participations', function ($q) use ($participant) {
            $q->where('participant_id', $participant->id)
              ->where('stage', PipelineStage::COMPLETED->value);
        })->first();

        if ($study) {
            // Mirrors what Member 2's pipeline controller will eventually do:
            // move a participation to the completed stage. The observer
            // picks this up on its own — this line never touches karma_transactions.
            StudyParticipation::updateOrCreate(
                ['study_id' => $study->id, 'participant_id' => $participant->id],
                ['stage' => PipelineStage::COMPLETED, 'completed_at' => now()]
            );

            // Mirrors the still-unbuilt review-submission endpoint.
            StudyReview::firstOrCreate(
                ['study_id' => $study->id, 'participant_id' => $participant->id, 'reviewer_role' => 'participant'],
                ['researcher_id' => $study->researcher_id, 'stars' => 5, 'comment' => 'Demo seed — great study experience.']
            );

            $this->command->info("Completed \"{$study->title}\" + left a review for it.");
        } else {
            $this->command->info('No uncompleted studies left to simulate — every seeded study is already completed for this participant.');
        }

        // ---- SESSION_ON_TIME — no real trigger exists in the schema,
        // so this is awarded directly, once. Guarded so re-running the
        // seeder doesn't add it a second time. ----
        $alreadySeeded = \App\Models\KarmaTransaction::where('user_id', $participant->id)
            ->where('source', KarmaSource::SESSION_ON_TIME->value)
            ->exists();

        if (! $alreadySeeded) {
            $karma->earn($participant, KarmaSource::SESSION_ON_TIME, 'Demo seed: on-time attendance');
        }

        $after = $karma->balanceFor($participant);

        $this->command->info("{$participant->name}: {$before} → {$after} karma.");
    }
}