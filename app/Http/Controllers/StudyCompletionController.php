<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\NotificationService;

/**
 * FEATURE — Participant Free-to-Paid Unlock Rule (Member 3)
 *
 * Fills the missing gap between a confirmed participation and a completed
 * one: the participant clicks "Mark as complete", the researcher who owns
 * the study approves or declines it. Only approval moves stage to
 * COMPLETED — and that update is what StudyParticipationObserver listens
 * for to run the free-to-paid unlock check, so nothing else needs to
 * change to wire this into that feature.
 */
class StudyCompletionController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /** POST /studies/{study}/complete — participant marks their session done */
    public function request(Study $study)
    {
        $participant = auth()->user();

        $participation = StudyParticipation::query()
            ->where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->firstOrFail();

        abort_unless(
            $participation->stage === PipelineStage::CONFIRMED,
            422,
            'Only confirmed participations can be marked complete.'
        );

        $participation->update(['completion_requested_at' => now()]);

        $this->notifications->send(
            $study->researcher,
            'studies',
            'Completion request: ' . $study->title,
            $participant->name . ' says they have completed "' . $study->title . '". Review and approve to confirm.',
            route('studies.show', $study)
        );

        return back()->with('status', 'Marked as complete — waiting for the researcher to confirm.');
    }

    /** POST /studies/{study}/participants/{participant}/approve */
    public function approve(Study $study, User $participant)
    {
        $researcher = auth()->user();
        abort_unless($researcher->id === $study->researcher_id, 403);

        $participation = StudyParticipation::query()
            ->where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->firstOrFail();

        $participation->update([
            'stage'                   => PipelineStage::COMPLETED,
            'completed_at'            => now(),
            'completion_requested_at' => null,
        ]);

        $this->notifications->send(
            $participant,
            'studies',
            'Completion approved: ' . $study->title,
            'Your completion of "' . $study->title . '" was approved.',
            route('studies.show', $study)
        );

        return back()->with('status', 'Marked ' . $participant->name . ' as completed.');
    }

    /** POST /studies/{study}/participants/{participant}/decline */
    public function decline(Study $study, User $participant)
    {
        $researcher = auth()->user();
        abort_unless($researcher->id === $study->researcher_id, 403);

        $participation = StudyParticipation::query()
            ->where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->firstOrFail();

        $participation->update(['completion_requested_at' => null]);

        $this->notifications->send(
            $participant,
            'studies',
            'Completion declined: ' . $study->title,
            'Your completion request for "' . $study->title . '" was declined. Contact the researcher for details.',
            route('studies.show', $study)
        );

        return back()->with('status', 'Declined the completion request.');
    }
}