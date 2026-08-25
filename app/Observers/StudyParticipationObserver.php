<?php

namespace App\Observers;

use App\Enums\IncentiveType;
use App\Enums\KarmaSource;
use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Services\KarmaService;
use App\Services\FreeToPaidUnlockService;
use Illuminate\Support\Facades\Log;

/**
 * study_participations.stage is Member 2's column; no controller in this
 * codebase writes stage = completed yet. An observer reacts to the model
 * event itself, so karma fires the moment ANY code sets it — whoever
 * writes that code, whenever it ships.
 */
class StudyParticipationObserver
{
    public function __construct(
        private KarmaService $karma,
        private FreeToPaidUnlockService $unlock,
    ) {}

    public function updated(StudyParticipation $p): void { $this->awardIfJustCompleted($p); }
    public function created(StudyParticipation $p): void { $this->awardIfJustCompleted($p); }

    private function awardIfJustCompleted(StudyParticipation $participation): void
    {
        if ($participation->stage !== PipelineStage::COMPLETED) {
            return;
        }

        if ($participation->wasRecentlyCreated === false && ! $participation->wasChanged('stage')) {
            return;
        }

        try {
            $this->karma->earn(
                $participation->participant,
                KarmaSource::STUDY_COMPLETED,
                'Completed "'.($participation->study?->title ?? 'a study').'"'
            );
        } catch (\Throwable $e) {
            Log::warning('Karma award for study completion failed.', [
                'participation_id' => $participation->id, 'error' => $e->getMessage(),
            ]);
        }

        if ($participation->study?->incentive_type === IncentiveType::VOLUNTEER) {
            try {
                $this->unlock->recordVolunteerCompletion($participation->participant);
            } catch (\Throwable $e) {
                Log::warning('Volunteer cycle update failed.', [
                    'participation_id' => $participation->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}