<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Endorsement;
use App\Models\User;
use App\Services\EndorsementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API — Researcher Endorsements → Verified Participant badge  (Member 1)
 *
 * Like the credential API, this holds no rules of its own. Who is eligible,
 * how many endorsements the badge needs, and how many tags one endorsement
 * may carry all come from EndorsementService, which reads config/platform.php.
 * The page, this API, and the notification text can never disagree.
 *
 * THIS DRIVES THE WHOLE PAGE
 * --------------------------
 * resources/views/researcher/endorsements.blade.php ships with no data.
 *
 *     GET  /api/v1/endorsements/pending          -> queue, given, tags, limits
 *     GET  /api/v1/participants/{user}/endorsements -> one participant's standing
 *     POST /api/v1/endorsements                  -> record one, return new standing
 *
 * Anything the page displays must therefore appear in one of them — including
 * display strings. That is why headline, badge_text, initials and first_name
 * are built here: the moment JavaScript starts writing sentences, the wording
 * lives in two places and one of them goes stale.
 *
 * This is the first Member 1 endpoint that WRITES. Two consequences:
 *   - validation failures come back as 422, and the page shows them inline;
 *   - eligibility is re-checked server-side, because a browser can post
 *     anything it likes regardless of what the page rendered.
 */
class EndorsementApiController extends Controller
{
    public function __construct(private EndorsementService $endorsements) {}

    /**
     * GET /api/v1/endorsements/pending
     *
     * Everything the endorsement page needs except one participant's
     * standing: the queue of sessions awaiting an endorsement, the ones this
     * researcher has already given, the tag vocabulary, and the two limits.
     */
    public function pending(Request $request): JsonResponse
    {
        $researcher = $this->researcherOrFail($request);

        $queue = $this->endorsements->pendingFor($researcher)->map(fn ($session) => [
            'participation_id' => $session->id,
            'participant_id'   => $session->participant_id,
            'participant_name' => $session->participant?->name,
            'initials'         => $this->initials($session->participant?->name),
            'study_id'         => $session->study_id,
            'study_title'      => $session->study?->title,

            'stage'            => $session->stage instanceof \BackedEnum
                                    ? $session->stage->value
                                    : $session->stage,

            // Label from the enum, never retyped in JavaScript.
            'stage_label'      => $session->stage instanceof PipelineStage
                                    ? $session->stage->label()
                                    : (string) $session->stage,

            'completed_at'     => $session->completed_at?->toIso8601String(),

            // Pre-formatted, so the page does no date maths.
            'completed_ago'    => $session->completed_at?->diffForHumans(),
        ]);

        $given = Endorsement::with('participant:id,name')
            ->where('researcher_id', $researcher->id)
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn ($endorsement) => [
                'id'               => $endorsement->id,
                'participant_id'   => $endorsement->participant_id,
                'participant_name' => $endorsement->participant?->name,
                'tags'             => $endorsement->tags ?? [],
                'given_ago'        => $endorsement->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'data' => [
                'researcher_id'  => $researcher->id,
                'available_tags' => Endorsement::TAGS,
                'max_tags'       => $this->endorsements->maxTags(),
                'required'       => $this->endorsements->required(),
                'pending_count'  => $queue->count(),
                'pending'        => $queue,
                'given'          => $given,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/participants/{user}/endorsements
     *
     * One participant's standing: the ring, the counter, the badge state and
     * the tag cloud. Called on page load and again every time the researcher
     * picks a different person out of the queue — which is why the standing
     * panel can change without the page reloading.
     *
     * Viewable by: the participant themselves, any researcher, any admin.
     */
    public function standing(Request $request, User $user): JsonResponse
    {
        $this->assertCanView($request, $user);
        $this->participantOrFail($user);

        return response()->json(['data' => $this->standingPayload($user)], 200);
    }

    /**
     * POST /api/v1/endorsements
     *
     * Body: { participant_id, study_id, tags: [] }
     *
     * Returns the message the page shows in its banner, plus the endorsed
     * participant's fresh standing so the ring can animate immediately
     * without a second request.
     */
    public function store(Request $request): JsonResponse
    {
        $researcher = $this->researcherOrFail($request);

        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:users,id'],
            'study_id'       => ['nullable', 'integer', 'exists:studies,id'],
            'tags'           => ['required', 'array', 'min:1', 'max:' . $this->endorsements->maxTags()],
            'tags.*'         => ['string', Rule::in(Endorsement::TAGS)],
        ]);

        $participant = User::find($data['participant_id']);
        $this->participantOrFail($participant);

        // The database has a unique index on (participant, researcher, study),
        // but checking here gives a readable message instead of a 500.
        $duplicate = Endorsement::where('researcher_id', $researcher->id)
            ->where('participant_id', $participant->id)
            ->where('study_id', $data['study_id'] ?? null)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'You have already endorsed this participant for that session.',
                'errors'  => ['tags' => ['You have already endorsed this participant for that session.']],
            ], 422);
        }

        // Re-check eligibility server-side. The page only ever offers sessions
        // from this researcher's own queue, but the page is not the guard.
        $eligible = $this->endorsements->eligibleSession(
            $researcher,
            (int) $participant->id,
            isset($data['study_id']) ? (int) $data['study_id'] : null
        );

        if (! $eligible) {
            return response()->json([
                'message' => 'You can only endorse participants from your own completed sessions.',
                'errors'  => ['participant_id' => ['That session is not one of yours to endorse.']],
            ], 422);
        }

        $result = $this->endorsements->endorse(
            $researcher,
            $participant,
            $data['study_id'] ?? null,
            $data['tags']
        );

        $message = $result['justVerified']
            ? $participant->name . ' just reached ' . $result['required']
              . ' endorsements and is now a Verified Participant.'
            : 'Endorsement recorded — ' . $participant->name . ' is at '
              . $result['count'] . ' of ' . $result['required'] . '.';

        return response()->json([
            'message'       => $message,
            'just_verified' => $result['justVerified'],
            'count'         => $result['count'],
            'required'      => $result['required'],
            'data'          => $this->standingPayload($participant->fresh()),
        ], 201);
    }

    // -----------------------------------------------------------------
    // Shared response shape
    // -----------------------------------------------------------------

    private function standingPayload(User $participant): array
    {
        $count    = $this->endorsements->distinctEndorserCount($participant);
        $required = $this->endorsements->required();
        $remaining = max(0, $required - $count);
        $verified = (bool) $participant->participantProfile?->is_verified_participant;

        $tags = $this->endorsements->receivedTags($participant)
            ->map(fn ($times, $tag) => ['tag' => $tag, 'times' => $times])
            ->values();

        return [
            'participant_id'   => $participant->id,
            'participant_name' => $participant->name,
            'first_name'       => $this->firstName($participant->name),
            'initials'         => $this->initials($participant->name),

            'count'            => $count,
            'required'         => $required,
            'remaining'        => $remaining,
            'verified'         => $verified,
            'progress_percent' => (int) round(min(100, $count / max(1, $required) * 100)),

            // Sentences built here, so the wording lives in one place.
            'headline'         => $verified
                ? 'Verified Participant'
                : ($remaining === 1
                    ? '1 endorsement to go'
                    : $remaining . ' endorsements to go'),

            'badge_text'       => '◆ Verified Participant — ' . ($verified ? 'earned' : 'locked'),

            'tags'             => $tags,
        ];
    }

    // -----------------------------------------------------------------
    // Display helpers — mirrors of the Blade components, kept here so the
    // page never has to do string work in JavaScript.
    // -----------------------------------------------------------------

    /** Same rule as resources/views/components/avatar.blade.php. */
    private function initials(?string $name): string
    {
        $clean = preg_replace('/^dr\.?\s*/i', '', trim((string) $name));
        $parts = array_values(array_filter(preg_split('/\s+/', $clean)));

        if (! $parts) {
            return 'TR';
        }

        return strtoupper(
            substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : '')
        );
    }

    private function firstName(?string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name))));

        return $parts[0] ?? 'They';
    }

    // -----------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------

    private function researcherOrFail(Request $request): User
    {
        $actor = $request->user();

        abort_unless(
            $actor->role === UserRole::RESEARCHER,
            403,
            'Only researchers can endorse participants.'
        );

        return $actor;
    }

    private function participantOrFail(?User $user): void
    {
        abort_if(! $user, 404, 'No such user.');

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

        abort_unless($allowed, 403, 'You may not view this participant\'s endorsements.');
    }
}