<?php

namespace App\Http\Controllers;

/**
 * FEATURE — Study invitations, participant side  (web entry point)
 *
 * API-DRIVEN PAGE
 * ---------------
 * Notice what this controller does NOT do: it passes no data to the view.
 * No $invitations, no $studies. There is no second argument to view().
 *
 * Everything the page shows arrives as JSON from:
 *
 *     GET   /api/v1/invitations
 *     PATCH /api/v1/invitations/{invitation}
 *
 * This controller's only job is to serve the empty page shell and refuse
 * entry to an account with no participant profile. That check stays here
 * because it decides whether the PAGE exists at all — it is not data.
 *
 * WHAT THIS REPLACES
 * ------------------
 * ParticipantStudyInvitationController had a GET that only redirected, plus
 * a POST to accept and a POST to decline, each writing
 * study_participations.stage directly. All three are deleted.
 */
class InvitationController extends Controller
{
    public function index()
    {
        abort_if(
            ! auth()->user()->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return view('participant.invitations');
    }
}
