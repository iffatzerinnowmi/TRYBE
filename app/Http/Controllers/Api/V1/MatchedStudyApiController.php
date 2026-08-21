<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
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
     * GET /api/v1/studies/{study}/requirements
     *
     * "What does this study ask for, and which of it do I meet?"
     *
     * WHY THIS EXISTS SEPARATELY FROM matched-studies
     * -----------------------------------------------
     * recommendStudiesForParticipant() deliberately excludes studies the
     * participant is already in, so the moment somebody applies, their match
     * detail disappears from that payload. This endpoint answers for ONE
     * study regardless of participation, which is what a study page needs.
     *
     * It is also the answer to a fair question in a demo: "is that skill gap
     * real, or hardcoded?" The requirements are read from
     * study_match_criteria and diffed against the participant's own profile,
     * and this endpoint shows both sides of that diff on screen.
     *
     * WHAT IT DELIBERATELY DOES NOT EXPOSE: the researcher's shortlist, other
     * candidates, or anyone else's scores. Only this participant's own
     * standing against published eligibility rules.
     */
    public function requirements(Request $request, Study $study): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::PARTICIPANT, 403,
            'Only participants have study requirements.');

        abort_if(! $user->participantProfile, 404,
            'No participant profile found for this account.');

        $criteria = $this->matching->criteriaForStudy($study);
        $assessed = $this->matching->assessUserForStudy($user, $criteria);
        $factors  = $assessed['factors'] ?? [];

        $skills = $factors['skills'] ?? [];

        // One row per required skill, each marked met or not. Built from the
        // matcher's own matched/missing arrays rather than re-diffed here, so
        // this display can never disagree with the score beside it.
        $matched = collect($skills['matched'] ?? [])->map(fn ($s) => (string) $s);

        $skillRows = collect($criteria['required_skills'] ?? [])
            ->map(fn ($skill) => [
                'skill' => (string) $skill,
                'met'   => $matched->contains((string) $skill),
            ])->values()->all();

        return response()->json([
            'data' => [
                'study_id'         => $study->id,
                'match_score'      => round((float) ($assessed['score'] ?? 0), 1),
                'strong_threshold' => $this->matching->strongThreshold(),
                'strong_match'     => ($assessed['score'] ?? 0) >= $this->matching->strongThreshold(),

                // True when the researcher never set criteria and the platform
                // defaults are standing in. Say so rather than presenting
                // defaults as though somebody chose them.
                'is_default'       => (bool) ($criteria['is_default'] ?? false),
                'summary'          => $this->matching->criteriaSummary($criteria),

                'skills' => [
                    'required'      => $skillRows,
                    'met_count'     => collect($skillRows)->where('met', true)->count(),
                    'total'         => count($skillRows),
                    'missing'       => array_values($skills['missing'] ?? []),
                    'your_skills'   => $this->matching->normaliseSkillList(
                        $user->participantProfile->skills
                    ),
                ],

                // The other published criteria, each with whether it is met.
                // Values the participant can act on; nothing about anyone else.
                'criteria' => [
                    'age_range'         => $this->ageLabel($criteria),
                    'age_met'           => ($factors['age']['sub_score'] ?? 0) >= 100,
                    'location'          => $criteria['location'],
                    'location_met'      => ($factors['location']['sub_score'] ?? 0) >= 100,
                    'credential_min'    => $criteria['credential_min'],
                    'credential_met'    => ($factors['credential']['sub_score'] ?? 0) >= 100,
                    'availability_days' => $criteria['availability_days'],
                    'topics'            => $criteria['topics'],
                ],
            ],
        ], 200);
    }

    private function ageLabel(array $criteria): ?string
    {
        $min = $criteria['age_min'] ?? null;
        $max = $criteria['age_max'] ?? null;

        if ($min === null && $max === null) {
            return null;
        }

        return ($min ?? 'any') . ' – ' . ($max ?? 'any');
    }

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
