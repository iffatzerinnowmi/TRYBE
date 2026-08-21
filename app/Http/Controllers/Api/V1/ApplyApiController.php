<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Services\StudyApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Apply to Study, volunteer studies only  (Member 4)
 *
 * No rules live here. The controller guards the role, calls
 * StudyApplicationService and shapes JSON. Every decision about whether an
 * application is allowed is in the service, so the button, the endpoint and
 * the tests cannot drift apart.
 *
 * WHY THE PAID REFUSAL IS SERVER-SIDE
 * -----------------------------------
 * The button for a paid study renders disabled — but a disabled button is a
 * UX affordance, not a guard. Anyone can POST to this route directly. So
 * store() refuses paid studies with a 422 regardless of what the client
 * sends, and a test asserts it. When Member 3's paid eligibility surface
 * lands, that refusal is the one branch that changes.
 */
class ApplyApiController extends Controller
{
    public function __construct(private StudyApplicationService $applications) {}

    /**
     * GET /api/v1/studies/{study}/apply-status
     *
     * What the button should render. The JavaScript makes no decisions of its
     * own — including the label, because button text is a product decision and
     * having it in one place stops the feed card and the study page disagreeing.
     */
    public function show(Request $request, Study $study): JsonResponse
    {
        $user = $this->applications->assertParticipant($request->user());

        return response()->json([
            'data' => $this->applications->status($user, $study),
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/apply
     *
     * Empty body. Everything needed is the authenticated user and the route
     * binding — there is nothing for a client to get wrong, and nothing it
     * could send that would change the outcome.
     */
    public function store(Request $request, Study $study): JsonResponse
    {
        $user = $this->applications->assertParticipant($request->user());

        $participation = $this->applications->apply($user, $study);

        return response()->json([
            'message' => 'Applied. The researcher will review your application.',
            'data'    => [
                'participation_id' => $participation->id,
                'study_id'         => $study->id,
                'stage'            => $participation->stage->value,
                'applied_at'       => $participation->created_at?->toIso8601String(),

                // The refreshed button state, so the page updates without a
                // second round trip and cannot briefly show a stale "Apply".
                'status' => $this->applications->status($user->fresh(), $study),
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/studies/{study}/apply
     *
     * Withdraw, allowed only while the stage is still `applied`.
     */
    public function destroy(Request $request, Study $study): JsonResponse
    {
        $user = $this->applications->assertParticipant($request->user());

        $this->applications->withdraw($user, $study);

        return response()->json([
            'message' => 'Application withdrawn.',
            'data'    => ['status' => $this->applications->status($user->fresh(), $study)],
        ], 200);
    }

    /**
     * GET /api/v1/participants/me/applications
     *
     * Read-only. A participant never changes their own stage.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $this->applications->assertParticipant($request->user());

        $rows = $this->applications->myApplications($user);

        return response()->json([
            'data' => [
                'count'        => $rows->count(),
                'applications' => $rows->map(fn (StudyParticipation $p) => [
                    'participation_id' => $p->id,
                    'study_id'         => $p->study_id,
                    'title'            => $p->study?->title,
                    'incentive_type'   => $p->study?->incentive_type?->value,
                    'study_status'     => $p->study?->status?->value,
                    'stage'            => $p->stage->value,
                    'applied_at'       => $p->created_at?->toIso8601String(),
                    'applied_on'       => $p->created_at?->format('d M Y'),
                    'url'              => route('studies.show', $p->study_id),
                ])->all(),
            ],
        ], 200);
    }
}
