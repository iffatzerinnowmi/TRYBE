<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyMatchCriteria;
use App\Models\StudyParticipation;
use App\Models\Topic;
use App\Models\User;
use App\Services\StudyInvitationService;
use App\Services\StudyMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * API — Smart Participant Matching  (Member 4, graded feature)
 *
 * This controller contains NO matching logic. Every number comes from
 * StudyMatchingService, which is the same class the pages use, so the web
 * page and the API cannot disagree about a candidate's score.
 *
 * JSON keys match database column names exactly (credential_level,
 * reliability_score, completed_studies_count, participant_id) so nobody has
 * to guess what a field is called.
 *
 * THIS PAYLOAD DRIVES THE WHOLE PANEL
 * -----------------------------------
 * resources/views/researcher/partials/candidates-panel.blade.php ships with
 * no data at all — it fetches this endpoint and renders from the JSON. So
 * anything the panel needs must appear here, including the weights table and
 * the criteria summary.
 *
 * THE ARITHMETIC RECONCILES
 * -------------------------
 * Each candidate's eight factor contributions add up to their match_score.
 * Factors that do not apply to the study (no topics tagged, no location
 * required) are marked applicable=false and their weight is redistributed,
 * so the total is always out of 100. Open the JSON in the viva and add the
 * contributions up.
 */
class CandidateApiController extends Controller
{
    public function __construct(
        private StudyMatchingService $matching,
        private StudyInvitationService $invitations,
    ) {}

    /**
     * GET /api/v1/studies/{study}/candidates
     *
     * The ranked candidate list. Query: ?limit= &include_declined=
     */
    public function index(Request $request, Study $study): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        $filters = $request->validate([
            'limit'            => ['sometimes', 'integer', 'between:1,50'],
            'include_declined' => ['sometimes', 'boolean'],
        ]);

        $limit           = (int) ($filters['limit'] ?? $this->matching->candidatesLimit());
        $includeDeclined = (bool) ($filters['include_declined'] ?? false);

        $criteria   = $this->matching->criteriaForStudy($study);
        $candidates = $this->matching->rankParticipantsForStudy($study, $limit, $includeDeclined);

        return response()->json([
            'data' => [
                'study_id'    => $study->id,
                'study_title' => $study->title,
                'status'      => $study->status?->value,
                'slots'       => (int) $study->slots,
                'seats_taken' => $this->invitations->seatsTaken($study),

                'criteria' => $this->criteriaPayload($criteria),
                'weights'  => $this->matching->weights(),
                'strong_threshold' => $this->matching->strongThreshold(),

                // Whether an accepted invitation currently reaches the
                // pipeline. False until Member 2 implements PipelineWriter.
                'pipeline_available' => $this->invitations->pipelineAvailable(),

                'count'      => $candidates->count(),
                'candidates' => $candidates->map(fn ($profile) => $this->candidatePayload($profile, $study))->all(),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/studies/{study}/candidates/{user}
     *
     * One candidate's full breakdown — powers the "View profile" page.
     */
    public function show(Request $request, Study $study, User $user): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        abort_unless($user->role === UserRole::PARTICIPANT, 404, 'That user is not a participant.');

        $profile = $user->participantProfile;
        abort_if(! $profile, 404, 'No participant profile found for that user.');

        $criteria   = $this->matching->criteriaForStudy($study);
        $assessment = $this->matching->assessUserForStudy($user, $criteria);
        $invitation = $this->invitations->statusFor($study, [$user->id])[$user->id] ?? null;

        return response()->json([
            'data' => [
                'study_id'    => $study->id,
                'study_title' => $study->title,
                'criteria'    => $this->criteriaPayload($criteria),
                'weights'     => $this->matching->weights(),
                'strong_threshold' => $this->matching->strongThreshold(),

                'user_id'                 => $user->id,
                'name'                    => $user->name,
                'location'                => $user->location,
                'age'                     => $profile->age !== null ? (int) $profile->age : null,
                'gender'                  => $profile->gender,
                'occupation'              => $profile->occupation,
                'skills'                  => $profile->skills,
                'interests'               => $profile->interests,
                'credential_level'        => $this->levelValue($profile->credential_level),
                'reliability_score'       => (int) $profile->reliability_score,
                'completed_studies_count' => (int) $profile->completed_studies_count,
                'endorsement_count'       => (int) $profile->endorsement_count,
                'current_streak_weeks'    => (int) $profile->current_streak_weeks,
                'is_verified_participant' => (bool) $profile->is_verified_participant,

                'match_score'   => $assessment['score'],
                'strong_match'  => $assessment['score'] >= $this->matching->strongThreshold(),
                'match_reasons' => $assessment['reasons'],
                'factors'       => $assessment['factors'],

                'invitation' => $this->invitationPayload($invitation),

                'participation_history' => $this->historyFor($user),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/topics
     *
     * The shared topic vocabulary, so the criteria editor can offer real
     * options instead of a free-text box. Any logged-in user may read it —
     * it is reference data, not anybody's private information.
     */
    public function topics(): JsonResponse
    {
        return response()->json([
            'data' => Topic::orderBy('name')->get(['id', 'name', 'slug']),
        ], 200);
    }

    /**
     * GET /api/v1/studies/{study}/match-criteria
     */
    public function criteria(Request $request, Study $study): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        return response()->json([
            'data' => $this->criteriaPayload($this->matching->criteriaForStudy($study)),
        ], 200);
    }

    /**
     * PUT /api/v1/studies/{study}/match-criteria
     *
     * PUT rather than PATCH: the criteria row is replaced wholesale, so
     * sending the same body twice produces the same result.
     *
     * This endpoint exists because eligibility criteria logically belong on
     * Member 2's "post a study" form, which does not collect them yet. This
     * lets a researcher set them after posting without any change to her
     * form.
     */
    public function updateCriteria(Request $request, Study $study): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        $data = $request->validate([
            'age_min'           => ['nullable', 'integer', 'between:0,120'],

            // Deliberately NOT 'gte:age_min' — that rule misbehaves when
            // age_min is null, which is a legitimate "no age requirement".
            // The pair is compared below instead.
            'age_max'           => ['nullable', 'integer', 'between:0,120'],

            'location'          => ['nullable', 'string', 'max:120'],
            'credential_min'    => ['nullable', Rule::in(array_column(CredentialLevel::cases(), 'value'))],
            'required_skills'   => ['nullable', 'array', 'max:20'],
            'required_skills.*' => ['string', 'max:60'],
            'availability_days' => ['nullable', 'integer', 'between:1,365'],
            'topic_ids'         => ['nullable', 'array', 'max:10'],
            'topic_ids.*'       => ['integer', 'exists:topics,id'],
        ]);

        if (isset($data['age_min'], $data['age_max']) && $data['age_max'] < $data['age_min']) {
            return response()->json([
                'message' => 'The maximum age must not be lower than the minimum age.',
                'errors'  => ['age_max' => ['The maximum age must not be lower than the minimum age.']],
            ], 422);
        }

        StudyMatchCriteria::updateOrCreate(
            ['study_id' => $study->id],
            [
                'age_min'           => $data['age_min'] ?? null,
                'age_max'           => $data['age_max'] ?? null,
                'location'          => $data['location'] ?? null,
                'credential_min'    => $data['credential_min'] ?? null,
                'required_skills'   => $this->matching->normaliseSkillList($data['required_skills'] ?? []),
                'availability_days' => $data['availability_days'] ?? null,
            ]
        );

        // Topics live in their own pivot. Synced directly rather than through
        // a relationship, because adding one to the Study model would mean
        // editing Member 2's file.
        if (array_key_exists('topic_ids', $data)) {
            $this->syncStudyTopics($study, $data['topic_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Matching criteria saved.',
            'data'    => $this->criteriaPayload($this->matching->criteriaForStudy($study)),
        ], 200);
    }

    // -----------------------------------------------------------------
    // Shared shapes
    // -----------------------------------------------------------------

    private function criteriaPayload(array $criteria): array
    {
        return [
            'age_min'           => $criteria['age_min'],
            'age_max'           => $criteria['age_max'],
            'location'          => $criteria['location'],
            'credential_min'    => $criteria['credential_min'],
            'required_skills'   => $criteria['required_skills'],
            'availability_days' => $criteria['availability_days'],
            'topics'            => $criteria['topics'],

            // True when no study_match_criteria row exists, so the panel can
            // prompt the researcher instead of silently using defaults.
            'is_default' => $criteria['is_default'],
            'summary'    => $this->matching->criteriaSummary($criteria),
        ];
    }

    private function candidatePayload($profile, Study $study): array
    {
        $user = $profile->user;

        return [
            'user_id'                 => $profile->user_id,
            'name'                    => $user?->name,
            'location'                => $user?->location,
            'age'                     => $profile->age !== null ? (int) $profile->age : null,
            'credential_level'        => $this->levelValue($profile->credential_level),
            'reliability_score'       => (int) $profile->reliability_score,
            'completed_studies_count' => (int) $profile->completed_studies_count,

            'match_score'   => $profile->match_score,
            'strong_match'  => (bool) $profile->strong_match,
            'match_reasons' => $profile->match_reasons ?? [],
            'factors'       => $profile->match_factors ?? [],

            'invitation' => $profile->invitation,

            'links' => [
                'profile' => route('researcher.studies.participants.show', [$study, $profile->user_id]),
                'study'   => route('studies.show', $study),
            ],
        ];
    }

    private function invitationPayload($invitation): ?array
    {
        if (! $invitation) {
            return null;
        }

        return [
            'id'                    => $invitation->id,
            'status'                => $invitation->status->value,
            'status_label'          => $invitation->status->label(),
            'match_score_at_invite' => (int) $invitation->match_score_at_invite,
            'invited_at'            => $invitation->created_at?->toIso8601String(),
            'responded_at'          => $invitation->responded_at?->toIso8601String(),
        ];
    }

    /** Recent participation history, for the profile page. */
    private function historyFor(User $user): array
    {
        return StudyParticipation::with('study:id,title')
            ->where('participant_id', $user->id)
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(fn ($p) => [
                'study_id'     => $p->study_id,
                'study_title'  => $p->study?->title,
                'stage'        => $p->stage instanceof PipelineStage ? $p->stage->value : (string) $p->stage,
                'stage_label'  => $p->stage instanceof PipelineStage ? $p->stage->label() : (string) $p->stage,
                'completed_on' => $p->completed_at?->format('d M Y'),
            ])
            ->all();
    }

    private function syncStudyTopics(Study $study, array $topicIds): void
    {
        $now  = now();
        $rows = collect($topicIds)->unique()->values()
            ->map(fn ($id) => [
                'study_id'   => $study->id,
                'topic_id'   => (int) $id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        DB::transaction(function () use ($study, $rows) {
            DB::table('study_topic')->where('study_id', $study->id)->delete();

            if ($rows) {
                DB::table('study_topic')->insert($rows);
            }
        });
    }

    private function levelValue(mixed $level): string
    {
        return $level instanceof CredentialLevel
            ? $level->value
            : (string) ($level ?? CredentialLevel::NONE->value);
    }
}
