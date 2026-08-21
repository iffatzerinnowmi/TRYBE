<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;

/**
 * FEATURE — Competition & Hackathon Board  (web entry points, Member 4)
 *
 * API-DRIVEN PAGES
 * ----------------
 * Neither method passes data to its view. Every listing, badge, deadline and
 * button state arrives as JSON from:
 *
 *     GET    /api/v1/competitions
 *     GET    /api/v1/competitions/mine
 *     POST   /api/v1/competitions
 *     DELETE /api/v1/competitions/{id}
 *     POST   /api/v1/competitions/{id}/save
 *     DELETE /api/v1/competitions/{id}/save
 *     GET    /api/v1/participants/me/competitions
 *
 * These controllers only decide whether the PAGE exists. That is not data.
 */
class CompetitionController extends Controller
{
    /**
     * GET /competitions — the board.
     *
     * Open to every signed-in role. A researcher browsing the board is a
     * reasonable thing to want, and the "Post a competition" panel simply
     * does not render for anyone who may not post.
     */
    public function index()
    {
        return view('competitions.index');
    }

    /**
     * GET /participant/competitions — my saved list.
     */
    public function saved()
    {
        abort_unless(
            auth()->user()->role === UserRole::PARTICIPANT,
            403,
            'Only participants have a saved competitions list.'
        );

        return view('participant.competitions');
    }
}
