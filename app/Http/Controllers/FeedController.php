<?php

namespace App\Http\Controllers;

/**
 * FEATURE — Study Recommendation Feed  (web entry point)
 *
 * API-DRIVEN PAGE
 * ---------------
 * Notice what this controller does NOT do: it passes no data to the view.
 * No $studies, no $filters, no $advice. There is no second argument to
 * view().
 *
 * Everything arrives as JSON from:
 *
 *     GET  /api/v1/participants/me/feed
 *     GET  /api/v1/participants/me/feed/filters
 *     GET  /api/v1/participants/me/skill-gap
 *     POST /api/v1/participants/me/skill-gap/refresh
 *
 * This controller's only job is to serve the empty shell and refuse entry to
 * an account with no participant profile. That check decides whether the
 * PAGE exists — it is not data.
 */
class FeedController extends Controller
{
    public function index()
    {
        abort_if(
            ! auth()->user()->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return view('participant.feed');
    }
}
