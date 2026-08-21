<?php

namespace App\Http\Controllers;

/**
 * FEATURE — My studies  (web entry point, Member 4)
 *
 * API-DRIVEN PAGE
 * ---------------
 * No data is passed to the view. No $studies, no $claims. Everything arrives
 * as JSON from:
 *
 *     GET  /api/v1/participants/me/applications      (the studies I am in)
 *     GET  /api/v1/participants/me/completions       (my completion claims)
 *     GET  /api/v1/studies/{id}/completion           (per-study button state)
 *     POST /api/v1/studies/{id}/completion           (I submitted the form)
 *
 * This controller's only job is to serve the empty shell and refuse entry to
 * an account with no participant profile. That check decides whether the PAGE
 * exists — it is not data.
 *
 * WHY THIS PAGE EXISTS
 * --------------------
 * Until now nothing showed a participant the studies they are actually in.
 * /participant/invitations shows invitations; the dashboard shows counts.
 * There was no screen listing active participation, which is precisely where
 * a "complete this study" action belongs.
 */
class MyStudiesController extends Controller
{
    public function index()
    {
        abort_if(
            ! auth()->user()->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return view('participant.studies');
    }
}
