<?php

namespace App\Http\Controllers;

/**
 * FEATURE 2 — Participant Reliability Score  (web entry point)
 *
 * API-DRIVEN PAGE
 * ---------------
 * Notice what this controller does NOT do: it passes no data to the view.
 * No $score, no $breakdown, no $pool. There is no second argument to view().
 *
 * Every number on that page arrives as JSON from:
 *
 *     GET  /api/v1/participants/{user}/reliability
 *     POST /api/v1/participants/{user}/reliability/recalculate
 *
 * This controller's only job is to serve the empty page shell and to refuse
 * entry to anyone without a participant profile. That check stays here
 * because it decides whether the PAGE exists at all — it is not data.
 *
 * To prove the page is API-driven during evaluation: open the browser's
 * Network tab, reload, and watch the JSON request arrive after the HTML.
 * Then fire the same endpoint in Postman and compare the numbers.
 */
class ReliabilityController extends Controller
{
    public function index()
    {
        abort_if(
            ! auth()->user()->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return view('participant.reliability');
    }
}