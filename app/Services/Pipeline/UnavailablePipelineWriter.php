<?php

namespace App\Services\Pipeline;

use App\Contracts\PipelineWriter;
use App\Models\Study;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The fallback used while Member 2's PipelineService does not exist.
 *
 * It deliberately writes NOTHING. The alternative — this feature writing
 * study_participations.stage itself — is the "two writers on one column"
 * problem the team rules exist to prevent, and it is what the old
 * ParticipantStudyInvitationController used to do.
 *
 * The invitation is still recorded as accepted, so no data is lost. When the
 * real pipeline writer is bound, accepted invitations can be replayed.
 */
class UnavailablePipelineWriter implements PipelineWriter
{
    public function confirm(Study $study, User $participant): void
    {
        Log::warning('Pipeline writer unavailable: invitation accepted but not confirmed in the pipeline.', [
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'action_needed'  => 'Member 2 to implement App\\Contracts\\PipelineWriter on PipelineService.',
        ]);
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
