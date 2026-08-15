<?php

namespace App\Observers;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Services\FreeToPaidUnlockService;

class StudyParticipationObserver
{
    public function __construct(private FreeToPaidUnlockService $unlock) {}

    public function saved(StudyParticipation $participation): void
    {
        if (! $participation->wasChanged('stage')) return;
        if ($participation->stage !== PipelineStage::COMPLETED) return;

        $study = $participation->study;
        if (! $study || $study->incentive_type !== IncentiveType::VOLUNTEER) return;

        $participant = $participation->participant;
        if (! $participant || ! $participant->participantProfile) return;

        $this->unlock->evaluate($participant);
    }
}