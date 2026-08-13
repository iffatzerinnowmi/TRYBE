<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Participant Credentialing: Bronze / Gold / Expert  (Member 1)
 *
 * Like the reliability API, this holds no rules of its own. Thresholds come
 * from CredentialLevel::fromCompletions() via CredentialService, so the
 * Blade page, this API and the artisan command can never disagree.
 *
 * THIS DRIVES THE WHOLE PAGE
 * --------------------------
 * resources/views/participant/credentials.blade.php ships with no data. It
 * calls two endpoints on load:
 *
 *     GET .../credentials                     -> badge, ladder, progress
 *     GET .../credentials/completed-studies   -> the history list
 *
 * Anything the page displays must therefore appear in one of them —
 * including display labels, which is why stage_label and completed_on are
 * built here rather than in JavaScript. The enum owns its own labels.
 *
 * EARNED vs GRANTED
 * -----------------
 * A credential level can now sit ABOVE the completion count, because Member
 * 4's referral reward grants a tier and CredentialService honours it as a
 * floor. That means "have I unlocked this rung?" is no longer just a
 * comparison against the count — a granted Gold with two completions has
 * unlocked Gold without having earned it.
 *
 * Each rung therefore reports two flags:
 *
 *   unlocked  — do you have this tier, by any route?
 *   granted   — do you have it WITHOUT the completions to back it up?
 *
 * Without that second flag the page contradicts itself: a Gold badge sitting
 * above a greyed-out, locked Gold rung.
 */
class CredentialApiController extends Controller
{
    public function __construct(private CredentialService $credentials) {}

    /**
     * GET /api/v1/participants/{user}/credentials
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
     * POST /api/v1/participants/{user}/credentials/recalculate
     *
     * Recounts completed studies and re-derives the level. The level can go
     * up or stay the same; it never goes down.
     *
     * Allowed for: the participant themselves, or an admin.
     */
    public function recalculate(Request $request, User $user): JsonResponse
    {
        $this->assertCanModify($request, $user);
        $this->profileOrFail($user);

        $result = $this->credentials->recalculate($user);

        return response()->json([
            'message' => $result['promoted']
                ? 'Promoted from ' . $result['from']->label() . ' to ' . $result['to']->label() . '.'
                : 'Recounted: ' . $result['count'] . ' completed studies. Level unchanged ('
                  . $result['to']->label() . ').',
            'previous_level'  => $result['from']->value,
            'new_level'       => $result['to']->value,
            'promoted'        => $result['promoted'],
            'completed_count' => $result['count'],

            // What the count alone would have earned, and what a referral
            // granted. Useful in Postman for showing the floor at work.
            'earned_level'    => $result['earned']->value,
            'granted_level'   => $result['granted']?->value,

            'data'            => $this->payload($user->fresh()),
        ], 200);
    }

    /**
     * GET /api/v1/participants/{user}/credentials/completed-studies
     *
     * The studies that earned the current level. Useful as evidence behind
     * the badge, and it exercises the join with Member 2's studies table.
     */
    public function completedStudies(Request $request, User $user): JsonResponse
    {
        $this->assertCanView($request, $user);
        $this->profileOrFail($user);

        $participations = StudyParticipation::query()
            ->with('study.researcher:id,name')
            ->where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
            ->latest('completed_at')
            ->get();

        $studies = $participations->map(fn ($p) => [
            'study_id'        => $p->study_id,
            'title'           => $p->study?->title,
            'category'        => $p->study?->category,
            'researcher_id'   => $p->study?->researcher_id,
            'researcher_name' => $p->study?->researcher?->name,

            'stage'           => $p->stage instanceof \BackedEnum ? $p->stage->value : $p->stage,

            // Display label from the enum itself, so the page never has to
            // hardcode "Paid" or "Completed" in JavaScript.
            'stage_label'     => $p->stage instanceof PipelineStage
                                    ? $p->stage->label()
                                    : (string) $p->stage,

            'completed_at'    => $p->completed_at?->toIso8601String(),

            // Pre-formatted for display, so the page does no date maths.
            'completed_on'    => $p->completed_at?->format('d M Y'),
        ]);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'count'   => $studies->count(),
                'studies' => $studies,
            ],
        ], 200);
    }

    // -----------------------------------------------------------------
    // Shared response shape
    // -----------------------------------------------------------------

    private function payload(User $user): array
    {
        $profile = $user->participantProfile;

        $count   = (int) $profile->completed_studies_count;
        $current = $profile->credential_level;
        $next    = $this->credentials->nextTier($current);

        // A referral-granted tier, if any. Null for almost everyone.
        $granted      = $this->credentials->grantedLevel($user);
        $currentIndex = $this->credentials->levelIndex($current);

        $ladder = collect($this->credentials->ladder())->map(function ($tier) use ($count, $currentIndex) {
            $level = $tier['level'];

            // Two separate questions, and they can disagree.
            $earnedByCount = $count >= $level->minCompletions();
            $unlocked      = $earnedByCount
                             || $this->credentials->levelIndex($level) <= $currentIndex;

            return [
                'level'           => $level->value,
                'label'           => $level->label(),
                'min_completions' => $level->minCompletions(),
                'perk'            => $tier['perk'],

                'unlocked'        => $unlocked,

                // Held without the completions to back it up — the page
                // labels these "Granted" rather than "Earned".
                'granted'         => $unlocked && ! $earnedByCount,
            ];
        });

        return [
            'user_id'                 => $user->id,
            'name'                    => $user->name,
            'credential_level'        => $current->value,
            'credential_label'        => $current->label(),
            'completed_studies_count' => $count,
            'next_level'              => $next?->value,
            'next_level_label'        => $next?->label(),
            'studies_to_next_level'   => $next ? max(0, $next->minCompletions() - $count) : 0,
            'progress_percent'        => $this->credentials->progressPercent($count, $current),
            'is_max_level'            => $next === null,

            // The live count straight from study_participations. If this
            // differs from completed_studies_count, a recalculate is due.
            'live_completed_count'    => $this->credentials->completedCount($user),
            'thresholds'              => config('platform.credential_thresholds'),
            'ladder'                  => $ladder,

            // Cross-module: the tier a referral reward granted, and whether
            // the stored level already honours it. False here means a
            // recalculate is due before the reward shows up.
            'granted_level'           => $granted?->value,
            'granted_level_label'     => $granted?->label(),
            'grant_applied'           => $granted === null
                                         || $this->credentials->levelIndex($current)
                                            >= $this->credentials->levelIndex($granted),
        ];
    }

    // -----------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------

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

    private function assertCanView(Request $request, User $target): void
    {
        $actor = $request->user();

        $allowed = $actor->id === $target->id
            || in_array($actor->role, [UserRole::RESEARCHER, UserRole::ADMIN], true);

        abort_unless($allowed, 403, 'You may not view this participant\'s credentials.');
    }

    private function assertCanModify(Request $request, User $target): void
    {
        $actor = $request->user();

        $allowed = $actor->id === $target->id || $actor->role === UserRole::ADMIN;

        abort_unless($allowed, 403, 'You may not recalculate this participant\'s credentials.');
    }
}