<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\User;
use App\Services\ReliabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Participant Reliability Score  (Member 1, Module 2 graded feature)
 *
 * This controller contains NO scoring logic. Every number comes from
 * ReliabilityService, which is the same class the Blade page uses. The web
 * page and the API can therefore never disagree about a participant's score.
 *
 * JSON keys deliberately match the database column names exactly
 * (rel_attendance, rel_completion, rel_reviews, reliability_score) so that
 * nobody on the team has to guess what a field is called.
 *
 * STORED vs LIVE
 * --------------
 * Every factor is reported twice, and the distinction matters:
 *
 *   stored_value  — what is saved on participant_profiles right now. The
 *                   weighted contributions of the stored values always add
 *                   up to reliability_score, so the arithmetic reconciles.
 *
 *   live_value    — what the current participation and review data says.
 *                   If this differs from stored, the saved score is stale
 *                   and a recalculate is due. in_sync tells you at a glance.
 *
 * THIS PAYLOAD DRIVES THE WHOLE PAGE
 * ----------------------------------
 * resources/views/participant/reliability.blade.php ships with no data at
 * all — it fetches this endpoint and renders from the JSON. So anything the
 * page needs to display must appear here, including the weights table and
 * the seat-auction preview.
 */
class ReliabilityApiController extends Controller
{
    public function __construct(private ReliabilityService $reliability) {}

    /**
     * GET /api/v1/participants/{user}/reliability
     *
     * Viewable by: the participant themselves, any researcher, any admin.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertCanView($request, $user);
        $this->profileOrFail($user);

        return response()->json(['data' => $this->payload($user)], 200);
    }

    /**
     * POST /api/v1/participants/{user}/reliability/recalculate
     *
     * Recomputes all three factors from live participation and review data,
     * then saves them. Allowed for: the participant themselves, or an admin.
     */
    public function recalculate(Request $request, User $user): JsonResponse
    {
        $this->assertCanModify($request, $user);
        $this->profileOrFail($user);

        $result = $this->reliability->recalculate($user);

        return response()->json([
            'message' => $result['changed']
                ? 'Reliability recalculated: ' . $result['from'] . ' -> ' . $result['score'] . '.'
                : 'Reliability recalculated. Still ' . $result['score'] . '.',
            'previous_score' => $result['from'],
            'new_score'      => $result['score'],
            'changed'        => $result['changed'],
            'data'           => $this->payload($user->fresh()),
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/participants/{user}/reliability
     *
     * Admin-only manual override of the three factors. Send any subset —
     * anything you leave out keeps its current value. The total score is
     * always recomputed from the weights, never sent directly.
     *
     * This is the endpoint to use for a live demonstration: change one
     * factor and the total moves by exactly that factor's weighted share.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertAdmin($request);
        $this->profileOrFail($user);

        $values = $request->validate([
            'rel_attendance' => ['sometimes', 'integer', 'between:0,100'],
            'rel_completion' => ['sometimes', 'integer', 'between:0,100'],
            'rel_reviews'    => ['sometimes', 'integer', 'between:0,100'],
        ]);

        if (empty($values)) {
            return response()->json([
                'message' => 'Send at least one of rel_attendance, rel_completion or rel_reviews.',
            ], 422);
        }

        $result = $this->reliability->overrideFactors($user, $values);

        return response()->json([
            'message'        => 'Reliability factors overridden by admin.',
            'previous_score' => $result['from'],
            'new_score'      => $result['score'],
            'changed'        => $result['changed'],
            'data'           => $this->payload($user->fresh()),
        ], 200);
    }

    /**
     * GET /api/v1/studies/{study}/auction-ranking
     *
     * INTEGRATION ENDPOINT — reads a study (Member 2's module) and ranks
     * participants for its limited seats using this module's reliability
     * score. High-demand studies award seats by reliability, not first-come.
     *
     * Different from the 'auction' block inside payload() below: that one is
     * a generic preview for the participant's own page, this one is tied to
     * one real study's slot count.
     */
    public function auctionRanking(Request $request, Study $study): JsonResponse
    {
        $seats = max(0, (int) $study->slots);

        $profiles = ParticipantProfile::query()
            ->with('user:id,name')
            ->orderByDesc('reliability_score')
            ->orderBy('user_id')
            ->take(20)
            ->get();

        $ranking = $profiles->values()->map(fn ($profile, $index) => [
            'rank'              => $index + 1,
            'user_id'           => $profile->user_id,
            'name'              => $profile->user?->name,
            'reliability_score' => $profile->reliability_score,
            'credential_level'  => $profile->credential_level?->value,
            'wins_seat'         => $seats > 0 && $index < $seats,
        ]);

        return response()->json([
            'data' => [
                'study_id'        => $study->id,
                'study_title'     => $study->title,
                'status'          => $study->status,
                'seats_available' => $seats,
                'ranked_by'       => 'reliability_score',
                'ranking'         => $ranking,
            ],
        ], 200);
    }

    // -----------------------------------------------------------------
    // Shared response shape — used by show(), recalculate() and update()
    // so all three endpoints return identical keys.
    // -----------------------------------------------------------------

    private function payload(User $user): array
    {
        $profile     = $user->participantProfile;
        $breakdown   = $this->reliability->breakdown($user);
        $liveScore   = $this->reliability->score($user);
        $storedScore = (int) $profile->reliability_score;
        $band        = $this->reliability->band($storedScore);

        // Service keys are attendance/completion/reviews.
        // JSON keys are the database column names, so nothing is ambiguous.
        $map = [
            'rel_attendance' => 'attendance',
            'rel_completion' => 'completion',
            'rel_reviews'    => 'reviews',
        ];

        $factors      = [];
        $factorsTotal = 0;
        $liveTotal    = 0;

        foreach ($map as $column => $serviceKey) {
            $factor = $breakdown[$serviceKey];

            $storedValue = (int) $profile->{$column};
            $weight      = (int) $factor['weight'];

            // Contribution is derived from the STORED value, so the three
            // contributions always add up to reliability_score.
            $contribution = round($storedValue * $weight / 100, 1);
            $factorsTotal += $contribution;
            $liveTotal    += $factor['contribution'];

            $factors[$column] = [
                'key'               => $serviceKey,
                'label'             => $factor['label'],
                'description'       => $factor['desc'],
                'weight'            => $weight,

                // Saved on the profile — these reconcile with the total.
                'stored_value'      => $storedValue,
                'contribution'      => $contribution,

                // What the live data says right now.
                'live_value'        => $factor['value'],
                'live_contribution' => $factor['contribution'],

                'has_data'          => $factor['hasData'],
                'detail'            => $factor['detail'],
            ];
        }

        return [
            'user_id'           => $user->id,
            'name'              => $user->name,

            // The saved score. Equals the sum of the contributions above.
            'reliability_score' => $storedScore,
            'factors_total'     => round($factorsTotal, 1),

            'band' => [
                'label'   => $band['label'],
                'tone'    => $band['tone'],
                'message' => $band['msg'],
            ],

            'factors' => $factors,

            // The raw weights table, so the page can show them and check
            // they still add up to 100 after a live edit.
            'weights' => array_map(
                'intval',
                config('platform.reliability_weights', [])
            ),

            'stored' => [
                'rel_attendance'    => (int) $profile->rel_attendance,
                'rel_completion'    => (int) $profile->rel_completion,
                'rel_reviews'       => (int) $profile->rel_reviews,
                'reliability_score' => $storedScore,
            ],

            // Where this participant would land in a 3-seat study.
            'auction' => $this->reliability->seatAuction($user),

            // What a recalculate would produce if run right now.
            'live_score' => $liveScore,
            'live_total' => round($liveTotal, 1),
            'in_sync'    => $liveScore === $storedScore,
        ];
    }

    // -----------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------

    /** The target user must actually be a participant with a profile row. */
    private function profileOrFail(User $user): void
    {
        abort_unless(
            $user->role === UserRole::PARTICIPANT,
            404,
            'That user is not a participant.'
        );

        abort_if(
            ! $user->participantProfile,
            404,
            'No participant profile found for that user.'
        );
    }

    /** Self, any researcher, or an admin. */
    private function assertCanView(Request $request, User $target): void
    {
        $actor = $request->user();

        $allowed = $actor->id === $target->id
            || in_array($actor->role, [UserRole::RESEARCHER, UserRole::ADMIN], true);

        abort_unless($allowed, 403, 'You may not view this participant\'s reliability.');
    }

    /** Self or an admin. */
    private function assertCanModify(Request $request, User $target): void
    {
        $actor = $request->user();

        $allowed = $actor->id === $target->id || $actor->role === UserRole::ADMIN;

        abort_unless($allowed, 403, 'You may not recalculate this participant\'s reliability.');
    }

    /** Admin only. */
    private function assertAdmin(Request $request): void
    {
        abort_unless(
            $request->user()->role === UserRole::ADMIN,
            403,
            'Admin only.'
        );
    }
}