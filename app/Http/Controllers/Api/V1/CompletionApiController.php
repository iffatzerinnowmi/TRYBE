<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyCompletionClaim;
use App\Services\StudyCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — completing a study, participant side  (Member 4)
 *
 * No rules here. Guard the role, call StudyCompletionService, shape JSON.
 *
 * WHAT THIS CONTROLLER NEVER DOES
 * -------------------------------
 * It never writes study_participations.stage. A claim is a statement, not a
 * completion — see StudyCompletionService for why that distinction is the
 * whole feature.
 */
class CompletionApiController extends Controller
{
    public function __construct(private StudyCompletionService $completion) {}

    /**
     * GET /api/v1/studies/{study}/completion
     *
     * What the button and the modal should render, including the form URL —
     * but only when the participant is actually allowed to act on it.
     */
    public function show(Request $request, Study $study): JsonResponse
    {
        $user = $this->completion->assertParticipant($request->user());

        return response()->json([
            'data' => $this->completion->status($user, $study),
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/completion
     *
     * "I submitted the form." Body: { note?: string }
     */
    public function store(Request $request, Study $study): JsonResponse
    {
        $user = $this->completion->assertParticipant($request->user());

        $data = $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:' . $this->completion->noteMax()],
        ]);

        $claim = $this->completion->claim($user, $study, $data['note'] ?? null);

        return response()->json([
            'message' => 'Thanks — the researcher has been told and will confirm your completion.',
            'data'    => [
                'claim'  => $claim->toPayload(),

                // The refreshed button state, so the page updates without a
                // second round trip and cannot briefly show a stale button.
                'status' => $this->completion->status($user->fresh(), $study),
            ],
        ], 201);
    }

    /**
     * PATCH /api/v1/studies/{study}/completion/opened
     *
     * Stamps that they followed the link. 204 because there is nothing to
     * say — and it is fire-and-forget from the client, which must not wait
     * on it before opening the form.
     */
    public function opened(Request $request, Study $study): JsonResponse
    {
        $user = $this->completion->assertParticipant($request->user());

        $this->completion->markFormOpened($user, $study);

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/participants/me/completions
     *
     * The participant's own claims. Read-only.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $this->completion->assertParticipant($request->user());

        $claims = $this->completion->claimsFor($user);

        return response()->json([
            'data' => [
                'count'  => $claims->count(),
                'claims' => $claims->map(fn (StudyCompletionClaim $c) => array_merge(
                    $c->toPayload(),
                    [
                        'study_id' => $c->study_id,
                        'title'    => $c->study?->title,
                        'url'      => route('studies.show', $c->study_id),
                    ]
                ))->all(),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/studies/{study}/completion-claims
     *
     * The researcher's view of claims on their own study.
     *
     * This exists so Member 2's pipeline can show a "claimed complete" marker
     * without joining my table. It is read-only and additive: if she never
     * calls it, nothing of hers changes.
     */
    public function forStudy(Request $request, Study $study): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user && $study->researcher_id === $user->id,
            403,
            'You may only view completion claims for your own studies.'
        );

        $claims = $this->completion->claimsForStudy($study);

        return response()->json([
            'data' => [
                'study_id' => $study->id,
                'count'    => $claims->count(),
                'claims'   => $claims->map(fn (StudyCompletionClaim $c) => array_merge(
                    $c->toPayload(),
                    [
                        'participant_id'   => $c->participant_id,
                        'participant_name' => $c->participant?->name,
                    ]
                ))->all(),
            ],
        ], 200);
    }
}
