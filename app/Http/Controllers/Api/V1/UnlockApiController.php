<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FreeToPaidUnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnlockApiController extends Controller
{
    public function __construct(private FreeToPaidUnlockService $unlock) {}

    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertCanView($request, $user);
        $this->profileOrFail($user);

        return response()->json([
            'data' => $this->payload($user)
        ], 200);
    }

    public function recalculate(Request $request, User $user): JsonResponse
    {
        $this->assertCanModify($request, $user);
        $this->profileOrFail($user);

        $result = $this->unlock->evaluate($user);

        $message = match (true) {
            $result['just_unlocked'] =>
                'Paid study access unlocked — that was the ' .
                $result['target'] .
                'th free study completed.',

            $result['unlocked'] =>
                'Already unlocked. Nothing changed.',

            default =>
                $result['remaining'] .
                ' more free stud' .
                ($result['remaining'] === 1 ? 'y' : 'ies') .
                ' to go.',
        };

        return response()->json([
            'message' => $message,
            'data' => $this->payload($user->fresh())
        ], 200);
    }

    private function payload(User $user): array
    {
        $profile = $user->participantProfile;
        $target = $this->unlock->target();
        $lifetimeCount = (int) $profile->free_studies_completed;

        $cycleProgress = $this->unlock->progress($user);

        return array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'free_studies_completed' => $lifetimeCount,
            'free_studies_required' => $target,

            'progress_percent' => $target > 0
                ? (int) min(
                    100,
                    round(
                        $cycleProgress['volunteer_progress']
                        / $target
                        * 100
                    )
                )
                : 100,

            'paid_studies_unlocked' =>
                (bool) $profile->paid_studies_unlocked,

            'unlocked_at' =>
                $profile->paid_studies_unlocked_at?->toIso8601String(),

            'live_free_completed_count' =>
                $this->unlock->freeCompletedCount($user),

        ], $cycleProgress);
    }

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

    private function assertCanView(
        Request $request,
        User $target
    ): void {
        $actor = $request->user();

        $allowed =
            $actor->id === $target->id ||
            in_array(
                $actor->role,
                [UserRole::RESEARCHER, UserRole::ADMIN],
                true
            );

        abort_unless(
            $allowed,
            403,
            "You may not view this participant's unlock status."
        );
    }

    private function assertCanModify(
        Request $request,
        User $target
    ): void {
        $actor = $request->user();

        $allowed =
            $actor->id === $target->id ||
            $actor->role === UserRole::ADMIN;

        abort_unless(
            $allowed,
            403,
            "You may not recalculate this participant's unlock status."
        );
    }
}