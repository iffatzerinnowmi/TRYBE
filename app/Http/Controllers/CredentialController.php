<?php

namespace App\Http\Controllers;

/**
 * FEATURE 1 — Participant Credentialing  (web entry point)
 *
 * API-DRIVEN PAGE
 * ---------------
 * No data is passed to the view. No $count, no $ladder, no $completed.
 * The page fetches everything from:
 *
 *     GET  /api/v1/participants/{user}/credentials
 *     GET  /api/v1/participants/{user}/credentials/completed-studies
 *     POST /api/v1/participants/{user}/credentials/recalculate
 *
 * The only thing decided here is whether the page exists at all — a user
 * with no participant profile has no credentials to show.
 */
class CredentialController extends Controller
{
    public function index()
    {
        abort_if(
            ! auth()->user()->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return view('participant.credentials');
    }
}