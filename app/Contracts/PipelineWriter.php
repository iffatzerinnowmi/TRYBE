<?php

namespace App\Contracts;

use App\Models\Study;
use App\Models\User;

/**
 * The seam between Smart Participant Matching (Member 4) and the participant
 * pipeline (Member 2).
 *
 * WHY THIS EXISTS
 * ---------------
 * When a participant accepts an invitation they should appear in the
 * researcher's pipeline as CONFIRMED. But study_participations.stage belongs
 * to Member 2's pipeline tracker, and the team rule is one writer per column
 * — "Need it changed? Call the service."
 *
 * There is no pipeline service in the codebase yet, so this interface states
 * exactly what the invitation feature needs from one. Member 2 implements it
 * on her PipelineService and the binding in AppServiceProvider picks it up
 * automatically, with no edit to any file this feature owns.
 *
 * Until then the container resolves UnavailablePipelineWriter, which records
 * nothing and logs a warning. Invitations still work; the accepted
 * participant simply does not appear in the pipeline yet. That is a visible,
 * documented gap rather than two features fighting over one column.
 */
interface PipelineWriter
{
    /**
     * Move a participant to the CONFIRMED stage for a study.
     *
     * Must be idempotent: calling it twice for the same pair is not an error.
     */
    public function confirm(Study $study, User $participant): void;

    /** Whether a real implementation is wired up, for reporting in the API. */
    public function isAvailable(): bool;
}
