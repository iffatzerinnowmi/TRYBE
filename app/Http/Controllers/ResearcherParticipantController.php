<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\StudyMatchingService;

class ResearcherParticipantController extends Controller
{
    public function show(Study $study, User $participant, StudyMatchingService $matching)
    {
        $researcher = auth()->user();

        abort_unless($researcher->id === $study->researcher_id, 403);
        abort_unless($participant->role === UserRole::PARTICIPANT, 422, 'Only participant accounts can be viewed here.');

        $profile = $participant->participantProfile;
        abort_if(! $profile, 404, 'Participant profile not found.');

        $criteria = $matching->criteriaForStudy($study);
        $match = $matching->assessUserForStudy($participant, $criteria);
        $participations = StudyParticipation::query()
            ->with('study')
            ->where('participant_id', $participant->id)
            ->latest('updated_at')
            ->take(8)
            ->get();

        return view('researcher.participants.show', [
            'user' => $researcher,
            'study' => $study->load('researcher'),
            'participant' => $participant,
            'profile' => $profile,
            'criteriaSummary' => $matching->criteriaSummary($criteria),
            'match' => $match,
            'participations' => $participations,
            'invited' => $matching->hasInvitation($participant, $study),
        ]);
    }
}
