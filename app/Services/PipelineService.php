<?php
namespace App\Services;

use App\Contracts\PipelineWriter;
use App\Enums\PipelineStage;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Member 2 implementation of the pipeline writer.
 *
 * Responsibilities:
 * - Idempotently ensure a participant is moved to CONFIRMED for a study.
 * - Never demote participants already at advanced stages (Scheduled, Completed, Paid).
 */
class PipelineService implements PipelineWriter
{
    public function confirm(Study $study, User $participant): void
    {
        $attributes = ['study_id' => $study->id, 'participant_id' => $participant->id];

        // If an advanced-stage participation already exists, do nothing.
        $existing = StudyParticipation::where($attributes)->first();

        if ($existing) {
            $advanced = [PipelineStage::SCHEDULED, PipelineStage::COMPLETED, PipelineStage::PAID];

            if (in_array($existing->stage, $advanced, true)) {
                Log::info('PipelineService: participant at advanced stage, skipping confirm.', [
                    'study_id' => $study->id,
                    'participant_id' => $participant->id,
                    'current_stage' => $existing->stage?->value ?? (string)$existing->stage,
                ]);
                return;
            }
        }

        // Idempotent: update existing non-advanced row or create a new one.
        StudyParticipation::updateOrCreate(
            $attributes,
            ['stage' => PipelineStage::CONFIRMED]
        );
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
