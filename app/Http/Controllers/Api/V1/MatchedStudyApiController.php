<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\StudyMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — the participant's half of Smart Participant Matching  (Member 4)
 *
 * The feature specification ends: "Participants who are a strong match also
 * see the study highlighted in their personalized recommendation feed."
 * This is that half. Same StudyMatchingService, same weights, same criteria
 * table — scored from the participant's side instead of the researcher's.
 *
 * It is also the seam into the Study Recommendation Feed feature: that will
 * add filters and ranking signals on top of this same ranked list.
 */
class MatchedStudyApiController extends Controller
{
    public function __construct(private StudyMatchingService $matching) {}

    /**
     * GET /api/v1/participants/me/matched-studies?limit=
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::PARTICIPANT, 403,
            'Only participants have matched studies.');

        abort_if(! $user->participantProfile, 404,
            'No participant profile found for this account.');

        $filters = $request->validate([
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $limit   = (int) ($filters['limit'] ?? $this->matching->candidatesLimit());
        $studies = $this->matching->recommendStudiesForParticipant($user, $limit);

        return response()->json([
            'data' => [
                'user_id'          => $user->id,
                'weights'          => $this->matching->weights(),
                'strong_threshold' => $this->matching->strongThreshold(),
                'count'            => $studies->count(),
                'studies'          => $studies->map(fn ($study) => [
                    'study_id'            => $study->id,
                    'title'               => $study->title,
                    'description'         => $study->description,
                    'category'            => $study->category,
                    'method'              => $study->method,
                    'duration_minutes'    => $study->duration_minutes,
                    'incentive_type'      => $study->incentive_type?->value,
                    'compensation_amount' => $study->compensation_amount,
                    'slots'               => (int) $study->slots,
                    'deadline'            => $study->deadline?->format('d M Y'),
                    'researcher_name'     => $study->researcher?->name,

                    'match_score'      => $study->match_score,
                    'strong_match'     => (bool) $study->strong_match,
                    'match_reasons'    => $study->match_reasons ?? [],
                    'factors'          => $study->match_factors ?? [],
                    'criteria_summary' => $study->criteria_summary,

                    'url' => route('studies.show', $study),
                ])->all(),
            ],
        ], 200);
    }
}
