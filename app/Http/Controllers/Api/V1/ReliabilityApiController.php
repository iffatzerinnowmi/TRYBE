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
                ? 'Reliability recalculated: ' . $result['from'] . ' → ' . $result['score'] . '.'
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
                'study_id'         => $study->id,
                'study_title'      => $study->title,
                'status'           => $study->status,
                'seats_available'  => $seats,
                'ranked_by'        => 'reliability_score',
                'ranking'          => $ranking,
            ],
        ], 200);
    }

    // -----------------------------------------------------------------
    // Shared response shape — used by show(), recalculate() and update()
    // so all three endpoints return identical keys.
    // -----------------------------------------------------------------

    private function payload(User $user): array
    {
        $profile   = $user->participantProfile;
        $breakdown = $this->reliability->breakdown($user);
        $liveScore = $this->reliability->score($user);
        $band      = $this->reliability->band($profile->reliability_score);

        // Service keys are attendance/completion/reviews.
        // JSON keys are the database column names, so nothing is ambiguous.
        $map = [
            'rel_attendance' => 'attendance',
            'rel_completion' => 'completion',
            'rel_reviews'    => 'reviews',
        ];

        $factors = [];

        foreach ($map as $column => $serviceKey) {
            $factor = $breakdown[$serviceKey];

            $factors[$column] = [
                'label'        => $factor['label'],
                'description'  => $factor['desc'],
                'value'        => $factor['value'],
                'weight'       => $factor['weight'],
                'contribution' => $factor['contribution'],
                'has_data'     => $factor['hasData'],
                'detail'       => $factor['detail'],
            ];
        }

        return [
            'user_id'           => $user->id,
            'name'              => $user->name,
            'reliability_score' => $profile->reliability_score,
            'band' => [
                'label'   => $band['label'],
                'tone'    => $band['tone'],
                'message' => $band['msg'],
            ],
            'factors' => $factors,

            // What is currently saved on the profile.
            'stored' => [
                'rel_attendance'    => $profile->rel_attendance,
                'rel_completion'    => $profile->rel_completion,
                'rel_reviews'       => $profile->rel_reviews,
                'reliability_score' => $profile->reliability_score,
            ],

            // What the live data says right now. If these differ, the stored
            // score is stale and a recalculate is due.
            'live_score' => $liveScore,
            'in_sync'    => $liveScore === (int) $profile->reliability_score,
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