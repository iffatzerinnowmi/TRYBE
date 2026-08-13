<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;

/**
 * FEATURE — Referral system  (web entry point)
 *
 * API-DRIVEN PAGES
 * ----------------
 * Notice what this controller does NOT do: it passes no data to either view.
 * No $code, no $progress, no $referrals. There is no second argument to
 * view().
 *
 * Everything arrives as JSON from:
 *
 *     GET  /api/v1/referrals/me
 *     POST /api/v1/referrals/me/code
 *     POST /api/v1/referrals/me/sync
 *     GET  /api/v1/researchers/me/post-credits
 *
 * The only job here is deciding whether each page exists at all, which is a
 * role check, not data.
 */
class ReferralController extends Controller
{
    /** Refer a Friend — participants. */
    public function index()
    {
        abort_if(
            ! auth()->user()->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return view('participant.referrals');
    }

    /** Referral rewards and free-post credits — researchers. */
    public function researcher()
    {
        abort_unless(
            auth()->user()->role === UserRole::RESEARCHER,
            403,
            'Only researchers have post credits.'
        );

        return view('researcher.referrals');
    }
}
