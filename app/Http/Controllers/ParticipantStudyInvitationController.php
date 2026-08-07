<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\UserNotification;
use App\Services\StudyMatchingService;

class ParticipantStudyInvitationController extends Controller
{
    public function show(Study $study, StudyMatchingService $matching)
    {
        $user = auth()->user();

        $invite = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'studies')
            ->where('title', $matching->invitationTitle($study))
            ->firstOrFail();

        $criteria = $matching->criteriaForStudy($study);
        $match = $matching->assessUserForStudy($user, $criteria);

        return view('participant.study-invitation', [
            'user' => $user,
            'study' => $study->load('researcher'),
            'invite' => $invite,
            'criteria' => $criteria,
            'criteriaSummary' => $matching->criteriaSummary($criteria),
            'match' => $match,
        ]);
    }

    public function accept(Study $study, StudyMatchingService $matching)
    {
        $user = auth()->user();

        $invite = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'studies')
            ->where('title', $matching->invitationTitle($study))
            ->firstOrFail();

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $user->id],
            ['stage' => PipelineStage::CONFIRMED->value]
        );

        $invite->update(['read_at' => now()]);

        return redirect()->route('participant.dashboard')->with('status', 'You accepted the invitation for ' . $study->title . '.');
    }

    public function decline(Study $study, StudyMatchingService $matching)
    {
        $user = auth()->user();

        $invite = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'studies')
            ->where('title', $matching->invitationTitle($study))
            ->firstOrFail();

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $user->id],
            ['stage' => PipelineStage::REJECTED->value]
        );

        $invite->update(['read_at' => now()]);

        return redirect()->route('participant.dashboard')->with('status', 'You declined the invitation for ' . $study->title . '.');
    }
}
