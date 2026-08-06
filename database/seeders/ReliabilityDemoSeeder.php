<?php

namespace Database\Seeders;

use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\StudyReview;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Extra dummy data for Member 1's reliability feature.
 *
 * The main DatabaseSeeder creates reviews that PARTICIPANTS wrote about
 * researchers. The reliability score needs the opposite direction —
 * researchers rating participants — plus a couple of missed sessions, or
 * every attendance score would be a flat 100%.
 *
 * Kept in its own file so the shared DatabaseSeeder is never edited.
 *
 *   php artisan db:seed --class=ReliabilityDemoSeeder
 */
class ReliabilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $researchers = User::where('role', UserRole::RESEARCHER)->get();
        $participants = User::where('role', UserRole::PARTICIPANT)->has('participantProfile')->get();

        if ($researchers->isEmpty() || $participants->isEmpty()) {
            $this->command->warn('Run the main DatabaseSeeder first.');
            return;
        }

        $studies = Study::all();

        /* ---- 1. Researchers rate the participants they worked with ---- */
        $made = 0;

        foreach ($participants as $participant) {
            $sessions = StudyParticipation::where('participant_id', $participant->id)
                ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
                ->get();

            foreach ($sessions as $index => $session) {
                $study = $studies->firstWhere('id', $session->study_id);
                $researcherId = $study?->researcher_id ?? $researchers->first()->id;

                StudyReview::firstOrCreate(
                    [
                        'study_id'       => $session->study_id,
                        'researcher_id'  => $researcherId,
                        'participant_id' => $participant->id,
                        'reviewer_role'  => 'researcher',
                    ],
                    [
                        // A spread of 3–5 stars so scores differ between people.
                        'stars'   => [5, 4, 5, 4, 3, 5][$index % 6],
                        'comment' => 'Turned up on time and followed the protocol.',
                    ]
                );

                $made++;
            }
        }

        /* ---- 2. A few missed sessions, so attendance isn't a flat 100% ---- */
        $noShows = 0;

        foreach ($participants->slice(3, 4) as $i => $participant) {
            $study = $studies->get($i % max(1, $studies->count()));

            if (! $study) {
                continue;
            }

            $exists = StudyParticipation::where('participant_id', $participant->id)
                ->where('study_id', $study->id)
                ->exists();

            if ($exists) {
                continue;
            }

            StudyParticipation::create([
                'study_id'       => $study->id,
                'participant_id' => $participant->id,
                'stage'          => PipelineStage::NO_SHOW,
            ]);

            $noShows++;
        }

        $this->command->info("Reliability demo data: {$made} researcher reviews, {$noShows} no-shows.");
        $this->command->info('Now run: php artisan trybe:recalculate');
    }
}
