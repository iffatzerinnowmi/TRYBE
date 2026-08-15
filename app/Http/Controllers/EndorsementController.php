<?php

namespace App\Http\Controllers;

/**
 * FEATURE 3 — Researcher endorsements  (web entry point)
 *
 * API-DRIVEN PAGE
 * ---------------
 * No data is passed to the view. No $pending, no $selected, no $standing,
 * no $allTags, no $maxTags. The page fetches everything from:
 *
 *     GET  /api/v1/endorsements/pending
 *     GET  /api/v1/participants/{user}/endorsements
 *     POST /api/v1/endorsements
 *
 * store() is gone entirely. Submitting an endorsement used to be a Blade
 * form POST to /researcher/endorsements; it is now a fetch() to the API, so
 * the web POST route was deleted from routes/web.php as well.
 *
 * The only thing decided here is whether the page exists at all, and the
 * role middleware on the route has already answered that.
 */
class EndorsementController extends Controller
{
    public function index()
    {
        return view('researcher.endorsements');
    }
}