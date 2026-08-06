<?php

namespace App\Http\Controllers;

use App\Models\ParticipantProfile;
use App\Services\ReliabilityService;

/**
 * FEATURE 2 — Participant Reliability Score.
 */
class ReliabilityController extends Controller
{
    public function __construct(private ReliabilityService $reliability) {}

    public function index()
    {
        $user = auth()->user();
        $profile = $user->participantProfile;

        abort_if(! $profile, 404, 'No participant profile found for this account.');

        $breakdown = $this->reliability->breakdown($user);
        $score = $profile->reliability_score;

        /**
         * Seat auction preview.
         *
         * High-demand studies with few seats are awarded by reliability rather
         * than first-come. These are REAL other participants' scores, so the
         * ranking is a genuine comparison, not a made-up leaderboard.
         */
        $seats = 3;

        $rivals = ParticipantProfile::query()
            ->with('user')
            ->where('user_id', '!=', $user->id)
            ->orderByDesc('reliability_score')
            ->take(12)
            ->get();

        $pool = $rivals->map(fn ($p) => [
            'name'  => $p->user->name,
            'score' => $p->reliability_score,
            'you'   => false,
        ])->push([
            'name'  => 'You',
            'score' => $score,
            'you'   => true,
        ])->sortByDesc('score')->values();

        $rank = $pool->search(fn ($row) => $row['you']) + 1;

        return view('participant.reliability', [
            'user'      => $user,
            'profile'   => $profile,
            'score'     => $score,
            'band'      => $this->reliability->band($score),
            'breakdown' => $breakdown,
            'weights'   => config('platform.reliability_weights'),
            'pool'      => $pool->take(6),
            'rank'      => $rank,
            'seats'     => $seats,
            'wonSeat'   => $rank <= $seats,
        ]);
    }

    /** Recalculate all three factors from live data and save them. */
    public function recalculate()
    {
        $result = $this->reliability->recalculate(auth()->user());

        $message = $result['changed']
            ? 'Reliability recalculated: ' . $result['from'] . ' → ' . $result['score'] . '.'
            : 'Reliability recalculated. Still ' . $result['score'] . '.';

        return back()->with('status', $message);
    }
}
