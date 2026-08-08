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
     * Recounts completed studies and re-derives the level.
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
            'completed_at'    => $p->completed_at?->toIso8601String(),
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

        $ladder = collect($this->credentials->ladder())->map(fn ($tier) => [
            'level'            => $tier['level']->value,
            'label'            => $tier['level']->label(),
            'min_completions'  => $tier['level']->minCompletions(),
            'perk'             => $tier['perk'],
            'unlocked'         => $count >= $tier['level']->minCompletions(),
        ]);

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