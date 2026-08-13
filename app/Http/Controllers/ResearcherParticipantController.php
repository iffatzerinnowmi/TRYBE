<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Study;
use App\Models\User;

/**
 * FEATURE — Smart Participant Matching: the candidate profile page
 *
 * API-DRIVEN PAGE
 * ---------------
 * Notice what this controller does NOT do: it passes no data to the view.
 * No $profile, no $match, no $participations, no $invited. There is no
 * second argument to view() beyond the two route models the page needs to
 * know WHICH candidate and WHICH study to ask the API about.
 *
 * Everything on the page arrives as JSON from:
 *
 *     GET  /api/v1/studies/{study}/candidates/{user}
 *     POST /api/v1/studies/{study}/invitations
 *
 * The checks below stay here because they decide whether the PAGE exists at
 * all — a researcher opening someone else's study, or a participant id that
 * is not a participant. That is not data.
 */
class ResearcherParticipantController extends Controller
{
    public function show(Study $study, User $participant)
    {
        abort_unless($study->researcher_id === auth()->id(), 403);

        abort_unless(
            $participant->role === UserRole::PARTICIPANT,
            404,
            'That user is not a participant.'
        );

        abort_if(
            ! $participant->participantProfile,
            404,
            'No participant profile found for that user.'
        );

        return view('researcher.participants.show', [
            'study'       => $study,
            'participant' => $participant,
        ]);
    }
}
